<?php

declare(strict_types=1);

namespace ITHilbert\UserAuth\Infrastructure\Auth;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Einzige Stelle, über die das Paket das User-Model des Hosts bezieht
 * (`auth.providers.users.model`) – das Paket kennt kein konkretes User-Model.
 */
final class UserModel
{
    private const CONFIG_KEY = 'auth.providers.users.model';

    public function __construct(private readonly Config $config) {}

    /**
     * @return class-string<Model>
     *
     * @throws UserModelMisconfiguredException
     */
    public function className(): string
    {
        $class = $this->config->get(self::CONFIG_KEY);

        if (! is_string($class) || $class === '') {
            throw new UserModelMisconfiguredException('Config "'.self::CONFIG_KEY.'" is not set; userauth needs the class of the user model.');
        }

        if (! class_exists($class)) {
            throw new UserModelMisconfiguredException("User model class '{$class}' (config \"".self::CONFIG_KEY.'") does not exist.');
        }

        if (! is_subclass_of($class, Model::class)) {
            throw new UserModelMisconfiguredException("User model class '{$class}' (config \"".self::CONFIG_KEY.'") is not an Eloquent model.');
        }

        return $class;
    }

    /**
     * @return Builder<Model>
     *
     * @throws UserModelMisconfiguredException
     */
    public function query(): Builder
    {
        return $this->className()::query();
    }

    /**
     * @throws UserModelMisconfiguredException
     */
    public function newInstance(): Model
    {
        $class = $this->className();

        return new $class;
    }
}
