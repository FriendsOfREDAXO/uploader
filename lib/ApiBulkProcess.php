<?php

namespace FriendsOfRedaxo\Uploader;

use rex;
use rex_api_function;
use rex_request;
use rex_response;

/**
 * API Endpunkt für die asynchrone Bulk-Verarbeitung
 */
class ApiBulkProcess extends rex_api_function
{
    public function execute()
    {
        rex_response::cleanOutputBuffers();
        
        // Nur für Backend-Nutzer mit entsprechenden Rechten
        if (!rex::isBackend() || !rex::getUser() || !rex::getUser()->hasPerm('uploader[bulk_rework]')) {
            $this->sendJsonResponse(false, 'Access denied');
        }

        $action = rex_request('action', 'string');
        
        switch ($action) {
            case 'start':
                $this->sendJsonResponse(true, $this->startBatch());
                break;
            case 'process':
                $this->sendJsonResponse(true, $this->processNext());
                break;
            case 'status':
                $this->sendJsonResponse(true, $this->getStatus());
                break;
            default:
                $this->sendJsonResponse(false, 'Unknown action');
        }
    }

    private function sendJsonResponse($success, $data)
    {
        rex_response::setHeader('Content-Type', 'application/json');
        echo json_encode([
            'success' => $success,
            'data' => $data
        ]);
        exit;
    }

    private function startBatch()
    {
        $filenames = rex_request('filenames', 'array', []);
        $maxWidth = rex_request('maxWidth', 'int', null);
        $maxHeight = rex_request('maxHeight', 'int', null);

        if (empty($filenames)) {
            return ['error' => 'No files provided'];
        }

        // Bereinige alte Batches
        BulkRework::cleanupOldBatches();

        $batchId = BulkRework::startBatchProcessing($filenames, $maxWidth, $maxHeight);
        
        return [
            'batchId' => $batchId,
            'status' => BulkRework::getBatchStatus($batchId)
        ];
    }

    private function processNext()
    {
        $batchId = rex_request('batchId', 'string');
        
        if (!$batchId) {
            return ['error' => 'No batch ID provided'];
        }

        // Verwende die neue parallele Verarbeitungsmethode
        $result = BulkRework::processNextBatchItems($batchId);
        
        return $result;
    }

    private function getStatus()
    {
        $batchId = rex_request('batchId', 'string');
        
        if (!$batchId) {
            return ['error' => 'No batch ID provided'];
        }

        // Verwende erweiterten Status für detailliertere Informationen
        $status = BulkRework::getBatchStatusExtended($batchId);
        
        if (!$status) {
            return ['error' => 'Batch not found'];
        }

        return $status;
    }
}
