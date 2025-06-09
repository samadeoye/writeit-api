<?php

declare(strict_types=1);

namespace App\Application\Actions\Journal;

use PDO;
use \PDOException;
use App\Application\Actions\Functions;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class JournalAction
{
    protected PDO $pdo;
    protected $table = 'journals';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Create a new journal
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return Response
     */
    public function createJournal(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        $data = $request->getParsedBody();
        $title = isset($data['title']) ? trim($data['title']) : '';
        $details = isset($data['details']) ? trim($data['details']) : '';
        $deviceId = $request->getHeaderLine('deviceId');
        $date = isset($data['date']) && !empty($data['date']) ? date('Y-m-d', strtotime($data['date'])) : date('Y-m-d');

        if (empty($title) || empty($details)) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Title and details are required'
            ], 400);
        }

        // Validate deviceId
        if (strlen($deviceId) != 36) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid device ID'
            ], 400);
        }

        try {
            $apiFields = JournalFunctions::getApiJournalFields();

            $stmt = $this->pdo->prepare("
                INSERT INTO $this->table (title, details, date, device_id)
                VALUES (:title, :details, :date, :deviceId)
            ");
            $stmt->execute([
                ':title' => $title,
                ':details' => $details,
                ':date' => $date,
                ':deviceId' => $deviceId
            ]);

            $id = $this->pdo->lastInsertId();
            $id = (int)$id;
            if ($id > 0)
            {
                $newEntry = $this->getJournalEntry(
                    $id, $deviceId, $apiFields
                );
                return Functions::getJsonResponse($response, $newEntry, 201);
            }
            else
            {
                return Functions::getJsonResponse($response, [
                    'status' => 'error',
                    'message' => 'Entry could not be added. Please try again.'
                ], 500);
            }

        } catch (PDOException $e) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing journal
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param mixed $args
     * @return Response
     */
    public function updateJournal(Request $request, Response $response, $args): Response
    {
        $id = (int) $args['id'];
        $data = $request->getParsedBody();
        $deviceId = $request->getHeaderLine('deviceId');

        $title = isset($data['title']) ? trim($data['title']) : '';
        $details = isset($data['details']) ? trim($data['details']) : '';
        $date = isset($data['date']) && !empty($data['date']) ? date('Y-m-d', strtotime($data['date'])) : '';

        if ($title == '' || $details == '' || $date == '') {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Title, details, and date are required'
            ], 400);
        }

        // Validate deviceId
        if (strlen($deviceId) != 36) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid device ID'
            ], 400);
        }

        try {
            // Check if journal exists and belongs to this deviceId
            $entryExists = $this->getJournalEntry(
                $id, $deviceId, 'id'
            );
            if (!$entryExists) {
                return Functions::getJsonResponse($response, [
                    'status' => 'error',
                    'message' => 'Journal not found for this device'
                ], 404);
            }

            // Perform update
            $stmt = $this->pdo->prepare("
                UPDATE $this->table 
                SET title = :title, date = :date, details = :details 
                WHERE id = :id AND device_id = :deviceId
            ");
            $stmt->execute([
                ':id' => $id,
                ':deviceId' => $deviceId,
                ':title' => $title,
                ':date' => $date,
                ':details' => $details
            ]);

            // Fetch updated journal
            $apiFields = JournalFunctions::getApiJournalFields();
            $newEntry = $this->getJournalEntry(
                $id, $deviceId, $apiFields
            );

            return Functions::getJsonResponse($response, $newEntry, 200);

        } catch (PDOException $e) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete journal
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param mixed $args
     * @return Response
     */
    public function deleteJournal(Request $request, Response $response, $args): Response
    {
        $id = (int) $args['id'];
        $deviceId = $request->getHeaderLine('deviceId');

        // Validate deviceId
        if (strlen($deviceId) != 36) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid device ID'
            ], 400);
        }

        try {
            // Check if the journal entry exists and belongs to the device
            $entryExists = $this->getJournalEntry(
                $id, $deviceId, 'id'
            );
            if (!$entryExists) {
                return Functions::getJsonResponse($response, [
                    'status' => 'error',
                    'message' => 'Journal not found or already deleted'
                ], 404);
            }

            // Do soft delete
            $stmt = $this->pdo->prepare("
                UPDATE $this->table 
                SET deleted = :deleted 
                WHERE id = :id AND device_id = :deviceId
            ");
            $stmt->execute([
                ':deleted' => 1,
                ':id' => $id,
                ':deviceId' => $deviceId
            ]);

            return Functions::getJsonResponse($response, [
                'status' => 'success',
                'message' => 'Deleted successfully'
            ]);

        } catch (PDOException $e) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get journal entry by id and device id
     * @param int $id
     * @param string $deviceId
     * @param string $fields

     * @return array
     */
    public function getJournalEntry(int $id, string $deviceId, string $fields='*')
    {
        $stmt = $this->pdo->prepare("
            SELECT $fields 
            FROM $this->table 
            WHERE id = :id AND device_id = :deviceId AND deleted = 0
        ");
        $stmt->execute([
            ':id' => $id
            , ':deviceId' => $deviceId
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}