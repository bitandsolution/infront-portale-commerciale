<?php

namespace App\Enums;

/**
 * The e-mail to the agent that waits for a document (D-221, D-474, D-477; F5-06), stored in
 * documents.pending_notification (C8): the approval and the new version are told when the PDF is
 * ready; after a failed generation the agent has been told without the PDF and waits for the
 * "document ready" e-mail of a successful regeneration.
 */
enum DocumentNotification: string
{
    case Approved = 'approved';
    case NewVersion = 'new_version';
    case DocumentReady = 'document_ready';
}
