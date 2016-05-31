<?php

/**
 * This file is part of the Twig Gettext utility.
 *
 *  (c) Saša Stamenković <umpirsky@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Twig\Gettext;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Extracts translations from twig templates.
 *
 * @author Saša Stamenković <umpirsky@gmail.com>
 */
class Extractor
{
    /**
     * @var \Twig_Environment
     */
    protected $environment;

    /**
     * Template cached file names.
     *
     * @var string[]
     */
    protected $templates;

    /**
     * Gettext parameters.
     *
     * @var string[]
     */
    protected $parameters;

    public function __construct(\Twig_Environment $environment)
    {
        $this->environment = $environment;
        $this->reset();
    }

    protected function reset()
    {
        $this->templates = array();
        $this->parameters = array();
    }

    public function addTemplate($path)
    {
        $this->environment->loadTemplate($path);
        $this->templates[] = $this->environment->getCacheFilename($path);
    }

    public function addGettextParameter($parameter)
    {
        $this->parameters[] = $parameter;
    }

    public function setGettextParameters(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function extract()
    {
        /**
         * Escape parameters so they can be executed in a shell.
         * For example, ensures that arguments like --copyright-holder='Zip Recipes' are escaped properly.
         * @param string $param Argument or option passed to a command
         *
         * @return string Escaped argument when escaping is needed
         */
        $escapeParams = function($param) {
            if ($param[0] !== "-") { // doesn't start with so must be an argument
                return escapeshellarg($param);
            }

            // escape part after =
            $parts = explode("=", $param);
            if (count($parts) > 1) {
                $parts[1] = escapeshellarg($parts[1]);
                return implode('=', $parts);
            }

            return $param;
        };

        $command = 'xgettext';
        $escapedParams = array_map($escapeParams, $this->parameters);
        $command .= ' '.implode(' ', $escapedParams);
        $escapedTemplates = array_map($escapeParams, $this->templates);
        $command .= ' '.implode(' ', $escapedTemplates);

        $error = 0;
        $output = system($command, $error);
        if (0 !== $error) {
            throw new \RuntimeException(sprintf(
                'Gettext command "%s" failed with error code %s and output: %s',
                $command,
                $error,
                $output
            ));
        }

        $this->reset();
    }

    public function __destruct()
    {
        $filesystem = new Filesystem();
        $filesystem->remove($this->environment->getCache());
    }
}
