<?php require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Finder\Finder;
use Robo\Tasks as TaskList;
use Robo\Task\GenMarkdownDocTask as Doc;
use Assetic\AssetWriter;
use Assetic\Extension\Twig\TwigFormulaLoader;
use Assetic\Extension\Twig\TwigResource;
use Assetic\Factory\LazyAssetManager;

class RoboFile extends TaskList
{
    const SECONDS = 1000000;

    const SERVER_PORT = 8083;

    /**
     * @desc Start the built-in PHP web server
     */
    public function serve()
    {
        $this->say("Starting the built-in server on localhost:" . self::SERVER_PORT);

        return $this->getServer()->run();
    }

    /**
     * @desc clears the cache
     */
    public function clean()
    {
        $except = [
            "./cache/assetic",
            "./cache/twig",
            "./cache/assetic/.gitignore",
            "./cache/twig/.gitignore",
        ];

        $files = array_diff(
            glob("./cache/{assetic/,twig/,}*", GLOB_BRACE),
            $except
        );

        $this->say(sprintf("Clearing %d cached files", $files));
        $this->taskDeleteDir($files)->run();

        $this->say("Clearing compiled CSS / JS");

        $compiledCss = glob(__DIR__ . "/public/css/style.min*.css");
        $compiledJs  = glob(__DIR__ . "/public/js/app.min*.js");

        $this->taskFileSystemStack()
            ->remove($compiledCss)
            ->remove($compiledJs)
            ->run();
    }

    /**
     * @desc Writes the assets to file
     */
    public function assets()
    {
        $app = include __DIR__ . "/bootstrap.php";

        $assetsManager = new LazyAssetManager($app["assetic.factory"]);

        // enable loading assets from twig templates
        $assetsManager->setLoader('twig', new TwigFormulaLoader($app["twig"]));

        // loop through all your templates
        foreach (array_diff(scandir(__DIR__ . "/views"), [".", ".."]) as $template) {
            $resource = new TwigResource($app["twig.loader"], $template);
            $assetsManager->addResource($resource, 'twig');
        }

        $writer = new AssetWriter($app["assetic.path_to_web"]);

        $this->say("Beginning asset dump process. Be patient!");

        $writer->writeManagerAssets($assetsManager);
    }

    /**
     * @desc Runs the test suite
     */
    public function test($args = "")
    {
        return $this->taskExec("./bin/phpunit")->args($args)->run();
    }

    /**
     * @desc runs the test suite with code coverage turned on
     */
    public function coverage($args = "")
    {
        return $this
            ->taskExec("./bin/phpunit")
            ->arg("--coverage-html")
            ->arg("./out/coverage")
            ->args($args)
            ->run();
    }

    /**
     * @desc watches the directory and reruns the tests when something is
     * changed
     */
    public function tdd($args = "")
    {
        $this->getServer()->background()->run();

        $this->test($args);

        $self    = $this;
        $files   = __DIR__ . "/tests/";

        return $this->taskWatch()
            ->monitor($files, function ($event) use ($args, $self) {
                $path = (string) $event->getResource()->getResource();
                $ext  = pathinfo($path, PATHINFO_EXTENSION);

                if (!in_array($ext, ["php", "twig", "json"])) {
                    return;
                }

                return $self->test($args);
            })
            ->run();
    }

    public function tags()
    {
        return $this->taskExec("phptags")->run();
    }

    private function getServer()
    {
        return $this->taskServer(self::SERVER_PORT)
            ->dir(__DIR__ . "/public")
            ->arg(__DIR__ . "/public/index.php");
    }
}
