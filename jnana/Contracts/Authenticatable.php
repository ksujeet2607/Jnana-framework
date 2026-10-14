<?php

namespace Jnana\Contracts;

/**
 * Marker interface for whatever "current user" object the consuming
 * application uses (a Doctrine entity, a DTO, etc).
 *
 * The framework never depends on a concrete User class — the app's
 * own user/entity class just needs to implement this.
 */
interface Authenticatable
{
}
