<?php

declare(strict_types=1);

namespace App\Application\Actions\Journal;

use PDO;
use App\Application\Actions\Functions;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ListJournalsAction
{
    private PDO $pdo;
    private $table = 'journals';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all journals
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return Response
     */
    public function __invoke(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $deviceId = $request->getHeaderLine('deviceId');
        
        $page = isset($queryParams['page']) && (int)$queryParams['page'] > 0 ? (int)$queryParams['page'] : 1;
        $limit = isset($queryParams['limit']) && (int)$queryParams['limit'] > 0 ? (int)$queryParams['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // Validate deviceId
        if (strlen($deviceId) != 36) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Device cannot be identified!',
                'data' => [],
                'totalCount' => 0
            ]);
        }

        try {
            $apiFields = JournalFunctions::getApiJournalFields();

            // Get total records for deviceId
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM $this->table WHERE device_id = :deviceId AND deleted = 0
            ");
            $stmt->execute([
                ':deviceId' => $deviceId
            ]);
            $totalCount = (int)$stmt->fetchColumn();

            // Get records based on pagination
            $stmt = $this->pdo->prepare("
                SELECT $apiFields FROM $this->table 
                WHERE device_id = :deviceId AND deleted = 0 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':deviceId', $deviceId);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $journals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return Functions::getJsonResponse($response, [
                'status' => 'success',
                'page' => $page,
                'limit' => $limit,
                'totalCount' => $totalCount,
                'data' => $journals
            ]);

        } catch (PDOException $e) {
            return Functions::getJsonResponse($response, [
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'data' => [],
                'totalCount' => 0
            ], 500);
        }
    }
}