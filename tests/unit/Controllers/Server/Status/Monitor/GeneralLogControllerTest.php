<?php

declare(strict_types=1);

namespace PhpMyAdmin\Tests\Controllers\Server\Status\Monitor;

use PhpMyAdmin\Config;
use PhpMyAdmin\Controllers\Server\Status\Monitor\GeneralLogController;
use PhpMyAdmin\Current;
use PhpMyAdmin\Dbal\DatabaseInterface;
use PhpMyAdmin\Http\Factory\ServerRequestFactory;
use PhpMyAdmin\Server\Status\Data;
use PhpMyAdmin\Server\Status\Monitor;
use PhpMyAdmin\Template;
use PhpMyAdmin\Tests\AbstractTestCase;
use PhpMyAdmin\Tests\Stubs\DbiDummy;
use PhpMyAdmin\Tests\Stubs\ResponseRenderer;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GeneralLogController::class)]
class GeneralLogControllerTest extends AbstractTestCase
{
    protected DatabaseInterface $dbi;

    protected DbiDummy $dummyDbi;

    private Data $data;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setGlobalConfig();

        $this->dummyDbi = $this->createDbiDummy();
        $this->dbi = $this->createDatabaseInterface($this->dummyDbi);
        DatabaseInterface::$instance = $this->dbi;

        Current::$database = 'db';
        Current::$table = 'table';
        $config = Config::getInstance();
        $config->selectedServer['DisableIS'] = false;
        $config->selectedServer['host'] = 'localhost';

        $this->data = new Data($this->dbi, $config);
    }

    public function testGeneralLog(): void
    {
        $value = ['sql_text' => 'insert sql_text', '#' => 10, 'argument' => 'argument argument2'];

        $value2 = ['sql_text' => 'update sql_text', '#' => 11, 'argument' => 'argument3 argument4'];

        $response = new ResponseRenderer();

        $dbi = DatabaseInterface::getInstance();
        $controller = new GeneralLogController(
            $response,
            new Template(),
            $this->data,
            new Monitor($dbi),
            $dbi,
        );

        $request = ServerRequestFactory::create()->createServerRequest('POST', 'https://example.com/')
        ->withParsedBody([
            'ajax_request' => 'true',
            'time_start' => '0',
            'time_end' => '10',
            'limitTypes' => '1',
        ]);

        $this->dummyDbi->addSelectDb('mysql');
        $controller($request);
        $this->dummyDbi->assertAllSelectsConsumed();
        $ret = $response->getJSONResult();

        $resultRows = [$value, $value2];
        $resultSum = ['argument' => 10, 'TOTAL' => 21, 'argument3' => 11];

        self::assertSame(2, $ret['message']['numRows']);
        self::assertEquals($resultRows, $ret['message']['rows']);
        self::assertEquals($resultSum, $ret['message']['sum']);
    }
}
