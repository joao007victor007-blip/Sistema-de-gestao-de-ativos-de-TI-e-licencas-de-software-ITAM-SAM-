<?php

namespace App\Exceptions;

/**
 * Regra de negócio violada numa movimentação (por exemplo, equipamento indisponível).
 * A mensagem é exibida ao usuário, por isso deve estar sempre em português claro.
 */
class MovementException extends \RuntimeException
{
}