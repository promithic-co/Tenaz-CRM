<?php

namespace App\Exceptions;

/**
 * The provider POST may or may not have reached Meta (request timeout, connection
 * reset after the request was written, or an HTTP 5xx). The outcome is undecidable,
 * so the caller MUST NOT blindly re-send — doing so risks delivering a duplicate
 * message. Callers should mark the row in_doubt and resolve it via a later webhook
 * status (echoing biz_opaque_callback_data) or reconciliation.
 */
class MetaAmbiguousSendException extends MetaApiException {}
