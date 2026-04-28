<?php
namespace Core2;

require_once 'Acl.php';
require_once "OpenApi.php";


use OpenApi\Generator;
use OpenApi\SourceFinder;

/**
 * @property \Core2\Model\Modules $dataModules
 */
class OpenApiSpec extends Acl {

    /**
     * @return array
     * @throws \Exception
     */
    public function getSections(): array {

        $sections = [
            [ 'name' => 'all', 'title' => 'Все', ],
            [ 'name' => 'core2', 'title' => 'Core2', ]
        ];

        $modules_list = $this->dataModules->getModuleList();
        $modules      = [];

        foreach ($modules_list as $module) {
            if ($this->checkAcl($module['module_id'])) {
                $modules[$module['module_id']] = $module;
            }
        }

        foreach ($modules as $mod) {
            $location        = $this->getModuleLocation($mod['module_id']);
            $controller_path = "{$location}/Mod" . ucfirst(strtolower($mod['module_id'])) . "Api.php";

            if (file_exists($controller_path)) {
                if (file_exists("{$location}/Api/schema.json")) {
                    $sections[$mod['module_id']] = [
                        'name'  => $mod['module_id'],
                        'title' => trim(strip_tags($mod['m_name']))
                    ];

                } else {
                    if ($this->issetSwaggerAnnotationsInFile($controller_path)) {
                        require_once $controller_path;
                        $scan = [__DIR__ . "/OpenApi.php", $controller_path];
                        if (is_dir("{$location}/Api")) {
                            // Добавляем все PHP файлы из папки Api
                            $apiFiles = glob("{$location}/Api/*.php");
                            if ($apiFiles) {
                                foreach ($apiFiles as $apiFile) {
                                    $scan[] = $apiFile;
                                }
                            }
                        }
                        $openapi        = (new Generator())->generate(new SourceFinder($scan, ['vendor'], '*.php'));
                        $section_scheme = $openapi->toJson();

                        if ( ! empty($section_scheme)) {
                            $section_scheme = json_decode($section_scheme, true);

                            if (count($section_scheme) > 1) {
                                $sections[$mod['module_id']] = [
                                    'name'  => $mod['module_id'],
                                    'title' => trim(strip_tags($mod['m_name']))
                                ];
                            }
                        }
                    }
                }
            }
        }


        return array_values($sections);
    }


    /**
     * @param string $section
     * @return array
     * @throws \Exception
     */
    public function getSectionSchema(string $section): array {

        if ($section == 'core2') {
            $section_schema = $this->getSchemeCore2();

        } elseif ($section == 'all') {
            $section_schema = $this->getSchemeAll();

        } else {
            $mods = $this->dataModules->getModuleList();

            foreach ($mods as $mod) {
                if ($mod['module_id'] == $section && $this->checkAcl($mod['module_id'])) {
                    $section_schema = $this->getSchemeModule($mod['module_id']);
                    break;
                }
            }
        }


        if ( ! empty($section_schema) && is_array($section_schema)) {

            $current_server = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}" . DOC_PATH;
            $current_server = rtrim($current_server, '/');

            $servers = [
                [ 'url' => $current_server ]
            ];

            if ( ! empty($section_schema['servers']) && is_array($section_schema['servers'])) {
                foreach ($section_schema['servers'] as $server) {
                    if ( ! empty($server['url']) && $current_server != rtrim($server['url'], '/')) {
                        $servers[] = $server;
                    }
                }
            }

            $section_schema['servers'] = $servers;

            return $section_schema;
        }

        return [];
    }


    /**
     * @return array
     */
    private function getSchemeCore2(): array {

        $schema_content = file_get_contents(__DIR__ . '/../../schema.json');
        return json_decode($schema_content, true);
    }


    /**
     * @return array
     * @throws \Exception
     */
    private function getSchemeAll(): array {

        $sections = [];
        $sections[] = ['title' => 'Core2', 'scheme' => $this->getSchemeCore2()];

        $mods = $this->dataModules->getModuleList();

        foreach ($mods as $mod) {
            if ($this->checkAcl($mod['module_id'])) {
                $scheme_module = $this->getSchemeModule($mod['module_id']);

                if ($scheme_module) {
                    $sections[] = ['title' => $mod['m_name'], 'scheme' => $scheme_module];
                }
            }
        }


        $result = $sections[0]['scheme'];
        $result['info']['title'] = $this->config->system->name;

        foreach ($sections as $section) {

            $scheme        = $section['scheme'];
            $section_title = trim(strip_tags($section['title']));

            if ( ! empty($scheme['components']) && ! empty($scheme['components']['schemas'])) {
                foreach ($scheme['components']['schemas'] as $component_name => $component_schema) {

                    if (empty($result['components']['schemas'][$component_name])) {
                        $result['components']['schemas'][$component_name] = $component_schema;
                    }
                }
            }

            if ( ! empty($scheme['paths'])) {
                foreach ($scheme['paths'] as $path => $path_schema) {

                    if (empty($result['paths'][$path])) {

                        foreach ($path_schema as $http_method => $method_scheme) {
                            $tags = ! empty($method_scheme['tags']) ? $method_scheme['tags'] : [];

                            if ( ! empty($tags[0])) {
                                if ( ! str_starts_with($tags[0], $section_title)) {
                                    $tags[0] = "{$section_title} - {$tags[0]}";
                                }

                            } else {
                                $tags[0] = $section_title;
                            }

                            $path_schema[$http_method]['tags'] = $tags;
                        }

                        $result['paths'][$path] = $path_schema;
                    }
                }
            }
        }

        return $result;
    }


    /**
     * @param string $module_name
     * @return array
     * @throws \Exception
     */
    private function getSchemeModule(string $module_name): array {

        $location    = $this->getModuleLocation($module_name);
        $file_schema = "{$location}/Api/schema.json";

        $section_schema = [];
        if (file_exists($file_schema)) {
            $schema_content = file_get_contents($file_schema);
            $section_schema = json_decode($schema_content, true);

        }
        $controller      = "Mod" . ucfirst(strtolower($module_name)) . "Api";
        $controller_path = "{$location}/{$controller}.php";

        if (file_exists($controller_path)) {
            require_once $controller_path;
            $scan = [__DIR__ . "/OpenApi.php", $controller_path];
            if (is_dir("{$location}/Api")) {
                $scan[] = "{$location}/Api/";
                // Добавляем все PHP файлы из папки Api
                $apiFiles = glob("{$location}/Api/*.php");
                if ($apiFiles) {
                    foreach ($apiFiles as $apiFile) {
                        require_once $apiFile;
                    }
                }
            }
            $openapi        = (new Generator())->generate(new SourceFinder($scan, ['vendor'], '*.php'));
            $schema = $openapi->toJson();
            if ( ! empty($schema)) {
                $schema = json_decode($schema, true);
                if (!$section_schema) {
                    return !empty($schema) ? $schema : [];
                }
            }
        }
        if (!empty($schema['paths'])) {
            $section_schema['paths'] = array_merge($section_schema['paths'], $schema['paths']);
        }
        if (!empty($schema['components'])) {
            $section_schema['components'] = array_merge($section_schema['components'], $schema['components']);
        }

        return $section_schema;
    }


    /**
     * @param string $filePath
     * @return bool
     */
    private function issetSwaggerAnnotationsInFile(string $filePath): bool {

        try {
            $context  = new \OpenApi\Context(['filename' => $filePath]);
            $analysis = new \OpenApi\Analysis([], $context);
            $analyser = new \OpenApi\Analysers\ReflectionAnalyser();

            $analysis->addAnalysis($analyser->fromFile($filePath, $context));

            $operations = $analysis->getAnnotationsOfType([
                \OpenApi\Annotations\Get::class,
                \OpenApi\Annotations\Post::class,
                \OpenApi\Annotations\Put::class,
                \OpenApi\Annotations\Delete::class,
                \OpenApi\Annotations\Patch::class,
                \OpenApi\Annotations\Options::class,
                \OpenApi\Annotations\Head::class,
                \OpenApi\Annotations\Trace::class,
            ]);

            return count($operations) > 0;

        } catch (\Exception $e) {
            // ignore
        }

        return false;
    }
}
