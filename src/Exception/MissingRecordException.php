<?php

namespace Simply\Database\Exception;

/**
 * Exception that gets thrown when trying to refresh, update or delete a record that no longer exists in the database.
 * @author Riikka Kalliomäki <riikka.kalliomaki@gmail.com>
 * @copyright Copyright (c) 2018 Riikka Kalliomäki
 * @license http://opensource.org/licenses/mit-license.php MIT License
 */
class MissingRecordException extends \RuntimeException
{
}
