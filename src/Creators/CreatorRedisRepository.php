<?php

namespace Nanvaie\DatabaseRepository\Creators;

use Illuminate\Support\Str;
use Nanvaie\DatabaseRepository\CustomMySqlQueries;

class CreatorRedisRepository implements IClassCreator
{
    public function __construct(
        public string $redisRepositoryName,
        public string $redisRepositoryNamespace,
        public string $entityName,
        public string $strategyName,
        public string $repositoryStubsPath,
    )
    {

    }
    public function getNameSpace(): string
    {
        return $this->redisRepositoryNamespace . '\\' . $this->entityName;
    }
    public function createUses(): array
    {
        return [
            "use Nanvaie\DatabaseRepository\Models\Repositories\RedisRepository;",
            "use App\Models\Repositories\Redis"."\\".$this->strategyName.";"
        ];
    }
    public function getClassName(): string
    {
        return $this->redisRepositoryName;
    }
    public function getExtendSection(): string
    {
        return "extends RedisRepository";
    }
    public function createAttributs(): array
    {
        return [];
    }
    public function createFunctions(): array
    {
        $constructStub = file_get_contents($this->repositoryStubsPath . 'construct_redis.stub');
        $functions = [];
        $functions['__construct'] = $this->getConstructRedis($constructStub);
        return $functions ;
    }
    public function getConstructRedis(string $constructStub)
    {
        return str_replace("{{Strategy}}",$this->strategyName,$constructStub);
    }

}
