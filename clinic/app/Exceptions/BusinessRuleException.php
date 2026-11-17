<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Vi phạm quy tắc nghiệp vụ (BR-xx). Được render thành thông báo lỗi thân thiện
 * (flash "error" khi là request web, JSON 422 khi là request AJAX).
 */
class BusinessRuleException extends RuntimeException
{
}
