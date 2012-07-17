<?php
require_once __DIR__.'/../vendor/autoload.php';


class AsseticGeneratorTest extends PHPUnit_Framework_TestCase
{
    public function testAsseticGeneratorLoads()
    {
        $app = new Silex\Application;
		$app->register(new AsseticGenerator\AsseticExtension(), array(
			'assetic.path_to_web' => __DIR__ . '/../www',
			'assetic.options' => array(
						'debug' => true
						)
			));

		$manager = $app['assetic.asset_manager'];

		$this->assertInstanceOf('Assetic\AssetManager', $manager);

    }
}