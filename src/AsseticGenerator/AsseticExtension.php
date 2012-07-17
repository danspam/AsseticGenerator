<?php

namespace AsseticGenerator;

use Silex\Application,
    Silex\ServiceProviderInterface;

use Symfony\Component\HttpFoundation\Response,
    Symfony\Component\HttpFoundation\Request;

use Assetic\AssetManager,
    Assetic\FilterManager,
    Assetic\AssetWriter,
    Assetic\Asset\AssetCache,
    Assetic\Factory\AssetFactory,
    Assetic\Factory\LazyAssetManager,
    Assetic\Cache\FilesystemCache,
    Assetic\Extension\Twig\AsseticExtension as TwigAsseticExtension,
    Assetic\Extension\Twig\TwigResource;

class AsseticExtension implements ServiceProviderInterface
{

    public function register(Application $app)
    {
        /**
         * Factory
         * @return Assetic\Factory\AssetFactory
         */
        $app['assetic.factory'] = $app->share(function() use ($app) {
            $options = $app['assetic.options'];
			$factory = new AssetFactory($app['assetic.path_to_web'], $options['debug']);
            $factory->setAssetManager($app['assetic.asset_manager']);
            $factory->setFilterManager($app['assetic.filter_manager']);

            return $factory;
        });

        /**
         * Asset writer, writes to the 'assetic.path_to_web' folder
         */
        $app['assetic.asset_writer'] = $app->share(function () use ($app) {
            return new AssetWriter($app['assetic.path_to_web']);
        });

        /**
         * Asset manager, can be accessed via $app['assetic.asset_manager']
         */
        $app['assetic.asset_manager'] = $app->share(function () use ($app) {
			return new AssetManager();
        });

        /**
         * Filter manager, can be accessed via $app['assetic.filter_manager']
         * and can be configured via $app['assetic.filters'], just provide a
         * protected callback $app->protect(function($fm) { }) and add
         * your filters inside the function to filter manager ($fm->set())
         */
        $app['assetic.filter_manager'] = $app->share(function () use ($app) {
            $filters = isset($app['assetic.filters']) ? $app['assetic.filters'] : function() {};
            $manager = new FilterManager();

            call_user_func_array($filters, array($manager));
            return $manager;
        });

		//optional OutputInterface can be passed to show progress
		$app['assetic.generate'] = $app->protect(function($output = null) use ($app) {
				if (!isset($app['twig'])) {
					return null;
				}

				$options  = $app['assetic.options'];
				$lazy = new LazyAssetmanager($app['assetic.factory']);

				$lazy->setLoader('twig', new \Assetic\Extension\Twig\TwigFormulaLoader($app['twig']));

				//get list of all twig views relative to the twig path and add them as resources
				$di = new \RecursiveDirectoryIterator($app['twig.path']);
				foreach (new \RecursiveIteratorIterator($di) as $filename => $file) {
					//needs path relative to twig path
					$relativepath = $filename;
					if (strpos($filename, $app['twig.path']) === 0)
					{
						$relativepath = substr($relativepath, strlen($app['twig.path']));
						$relativepath = trim($relativepath,'/\\');
					}

					$lazy->addResource(new TwigResource($app['twig']->getLoader(),$relativepath), 'twig');
				}

				$writeAsset = function($path, $contents, $output)
				{
					if (!is_dir($dir = dirname($path))) {

						if (isset($output))
							$output->writeln('<info>[dir+]</info>  '.$dir);

						if (false === @mkdir($dir, 0777, true)) {
							throw new \RuntimeException('Unable to create directory '.$dir);
						}
					}

					if (isset($output))
						$output->writeln('<info>[file+]</info> '.$path);

					if (false === @file_put_contents($path, $contents)) {
						throw new \RuntimeException('Unable to write file '.$path);
					}
				};

				foreach ($lazy->getNames() as $name) {

					$asset = $lazy->get($name);
					$formula = $lazy->getFormula($name);

					// dump the combined version
					$writeAsset($app['assetic.path_to_web'] . '/' . $asset->getTargetPath(), $asset->dump(), $output);

					// dump each leaf if debug
					if (isset($formula[2]['debug']) ? $formula[2]['debug'] : $options['debug']) {
						foreach ($asset as $leaf) {
							$writeAsset($app['assetic.path_to_web'] . '/' . $leaf->getTargetPath(), $leaf->dump(), $output);
						}
					}
				}
		});

    }

    public function boot(Application $app)
    {
        $app['assetic.options'] = array_replace(array(
            'debug' => false
        ), isset($app['assetic.options']) ? $app['assetic.options'] : array());

        //ensure the twig extension is loaded
        $app['twig']->addExtension(new TwigAsseticExtension($app['assetic.factory']));

    }

}
