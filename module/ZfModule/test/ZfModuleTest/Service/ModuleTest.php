<?php

namespace ZfModuleTest\Service;

use EdpGithub\Api;
use EdpGithub\Client;
use EdpGithub\Http\Client as HttpClient;
use PHPUnit_Framework_TestCase;
use stdClass;
use Zend\Http;
use ZfModule\Entity;
use ZfModule\Mapper;
use ZfModule\Service;
use ZfModuleTest\Mock;

class ModuleTest extends PHPUnit_Framework_TestCase
{
    public function testListAllModulesWithoutArgumentListsAllModulesFromDatabase()
    {
        $module = $this->getMockBuilder(Entity\Module::class)->getMock();

        $modules = [
            $module,
        ];

        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)->getMock();

        $moduleMapper
            ->expects($this->once())
            ->method('findAll')
            ->with(
                $this->equalTo(null),
                $this->equalTo('created_at'),
                $this->equalTo('DESC')
            )
            ->willReturn($modules)
        ;

        $githubClient = $this->getMockBuilder(Client::class)->getMock();

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $this->assertSame($modules, $service->allModules());
    }

    public function testListAllModulesWithArgumentListsModulesFromDatabaseLimited()
    {
        $limit = 9000;

        $module = $this->getMockBuilder(Entity\Module::class)->getMock();

        $modules = [
            $module,
        ];

        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)->getMock();

        $moduleMapper
            ->expects($this->once())
            ->method('findAll')
            ->with(
                $this->equalTo($limit),
                $this->equalTo('created_at'),
                $this->equalTo('DESC')
            )
            ->willReturn($modules)
        ;

        $githubClient = $this->getMockBuilder(Client::class)->getMock();

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $this->assertSame($modules, $service->allModules($limit));
    }

    public function testListUserModulesListsCurrentUsersModulesFromApiFoundInDatabase()
    {
        $name = 'foo';

        $repository = new stdClass();
        $repository->fork = false;
        $repository->permissions = new stdClass();
        $repository->permissions->push = true;
        $repository->name = $name;

        $module = $this->getMockBuilder(Entity\Module::class)->getMock();

        $modules = [
            $module,
        ];

        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)->getMock();

        $moduleMapper
            ->expects($this->once())
            ->method('findByName')
            ->with($this->equalTo($name))
            ->willReturn($module)
        ;

        $currentUserService = $this->getMockBuilder(Api\CurrentUser::class)->getMock();

        $currentUserService
            ->expects($this->once())
            ->method('repos')
            ->with($this->equalTo([
                'type' => 'all',
                'per_page' => 100,
            ]))
            ->willReturn(new Mock\Collection\RepositoryCollection([$repository]))
        ;

        $githubClient = $this->getMockBuilder(Client::class)->getMock();

        $githubClient
            ->expects($this->once())
            ->method('api')
            ->with($this->equalTo('current_user'))
            ->willReturn($currentUserService)
        ;

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $this->assertSame($modules, $service->currentUserModules());
    }

    public function testListUserModulesDoesNotLookupModulesFromApiWhereUserHasNoPushPrivilege()
    {
        $repository = new stdClass();
        $repository->fork = false;
        $repository->permissions = new stdClass();
        $repository->permissions->push = false;

        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)->getMock();

        $moduleMapper
            ->expects($this->never())
            ->method('findByName')
        ;

        $currentUserService = $this->getMockBuilder(Api\CurrentUser::class)->getMock();

        $currentUserService
            ->expects($this->once())
            ->method('repos')
            ->with($this->equalTo([
                'type' => 'all',
                'per_page' => 100,
            ]))
            ->willReturn(new Mock\Collection\RepositoryCollection([$repository]))
        ;

        $githubClient = $this->getMockBuilder(Client::class)->getMock();

        $githubClient
            ->expects($this->once())
            ->method('api')
            ->with($this->equalTo('current_user'))
            ->willReturn($currentUserService)
        ;

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $this->assertSame([], $service->currentUserModules());
    }

    public function testListUserModulesDoesNotLookupModulesFromApiThatAreForks()
    {
        $repository = new stdClass();
        $repository->fork = true;

        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)->getMock();

        $moduleMapper
            ->expects($this->never())
            ->method('findByName')
        ;

        $currentUserService = $this->getMockBuilder(Api\CurrentUser::class)->getMock();

        $currentUserService
            ->expects($this->once())
            ->method('repos')
            ->with($this->equalTo([
                'type' => 'all',
                'per_page' => 100,
            ]))
            ->willReturn(new Mock\Collection\RepositoryCollection([$repository]))
        ;

        $githubClient = $this->getMockBuilder(Client::class)->getMock();

        $githubClient
            ->expects($this->once())
            ->method('api')
            ->with($this->equalTo('current_user'))
            ->willReturn($currentUserService)
        ;

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $this->assertSame([], $service->currentUserModules());
    }

    public function testListUserModulesDoesNotListModulesFromApiNotFoundInDatabase()
    {
        $name = 'foo';

        $repository = new stdClass();
        $repository->fork = false;
        $repository->permissions = new stdClass();
        $repository->permissions->push = true;
        $repository->name = $name;

        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)->getMock();

        $moduleMapper
            ->expects($this->once())
            ->method('findByName')
            ->with($this->equalTo($name))
            ->willReturn(false)
        ;

        $currentUserService = $this->getMockBuilder(Api\CurrentUser::class)->getMock();

        $currentUserService
            ->expects($this->once())
            ->method('repos')
            ->with($this->equalTo([
                'type' => 'all',
                'per_page' => 100,
            ]))
            ->willReturn(new Mock\Collection\RepositoryCollection([$repository]))
        ;

        $githubClient = $this->getMockBuilder(Client::class)->getMock();

        $githubClient
            ->expects($this->once())
            ->method('api')
            ->with($this->equalTo('current_user'))
            ->willReturn($currentUserService)
        ;

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $this->assertSame([], $service->currentUserModules());
    }

    public function testIsModuleQueriesGitHubApi()
    {
        $moduleMapper = $this->getMockBuilder(Mapper\Module::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $repository = new stdClass();

        $repository->name = 'foo';

        $repository->owner = new stdClass();
        $repository->owner->login = 'suzie';

        $path = sprintf(
            'search/code?q=repo:%s/%s filename:Module.php "class Module"',
            $repository->owner->login,
            $repository->name
        );

        $response = $this->getMockBuilder(Http\Response::class)->getMock();

        $httpClient = $this->getMockBuilder(HttpClient::class)->getMock();

        $httpClient
            ->expects($this->once())
            ->method('request')
            ->with($this->equalTo($path))
            ->willReturn($response);

        $githubClient = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $githubClient
            ->expects($this->once())
            ->method('getHttpClient')
            ->willReturn($httpClient)
        ;

        $service = new Service\Module(
            $moduleMapper,
            $githubClient
        );

        $service->isModule($repository);
    }
}
