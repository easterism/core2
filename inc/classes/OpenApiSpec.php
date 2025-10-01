<?php
namespace Core2;
require_once 'Db.php';
use OpenApi\Attributes as OAT;

#[OAT\OpenApi(
    security: [['bearerAuth' => []]]
)]
#[OAT\Info(
    version: '2.9.0',
    description: 'Common API',
    title: 'CORE2',
    contact: new OAT\Contact(
        name: 'mister easter',
        email: 'easter.by@gmail.com'
    )
)]
#[OAT\Server(url: SERVER)]
#[OAT\Components(securitySchemes: [
        new OAT\SecurityScheme(
            type: "http",
            securityScheme: "bearerAuth",
            scheme: "bearer",
            in: "header",
            bearerFormat: "JWT"
        ),
        new OAT\SecurityScheme(
            type: "http",
            securityScheme: "basicAuth",
            scheme: "basic",
        )
    ]
)]
/**
 * @property \Core2\Model\Modules $dataModules
 */
class OpenApiSpec extends Db {

    private $_apis = [__FILE__];

    #[OAT\Get(
        path: '/',
        operationId: 'getModules',
        summary: 'Данные для главного меню',
        tags: ['core2'],
        responses: [
            new OAT\Response(
                response: 200,
                description: 'информация о пользователе и список доступных модулей',
                content: new OAT\JsonContent(
                    type: 'object',
                    properties: [
                        new OAT\Property(property: 'system_name', type: 'string', title: 'Название системы'),
                        new OAT\Property(property: 'id', type: 'integer', title: 'ID текущего пользователя'),
                        new OAT\Property(property: 'name', type: 'string', title: 'Имя текущего пользователя'),
                        new OAT\Property(property: 'login', type: 'string', title: 'Login текущего пользователя'),
                        new OAT\Property(property: 'avatar', type: 'string', title: 'ссылка на аватар'),
                        new OAT\Property(property: 'required_location', type: 'boolean', title: 'должен ли пользователь предоставить данные о местоположении'),
                        new OAT\Property(property: 'modules', title: 'System admin', type: 'object'),
                    ]

                ),
            ),
            new OAT\Response(
                response: 403,
                description: 'Unauthorized access',
            ),
        ]
    )]

    /**
     * @return string
     * @throws \Exception
     * @deprecated
     */
    public function render(): string {


        $this->module = 'admin';
        $mods     = $this->dataModules->getModuleList();
        foreach ($mods as $k => $data) {
            if (isset($this->_apis[$data['module_id']])) continue;
            $location      = $this->getModuleLocation($data['module_id']);
            $controller = "Mod" . ucfirst(strtolower($data['module_id'])) . "Api";
            if ( file_exists($location . "/$controller.php")) {
                require_once $location . "/$controller.php";
                $this->_apis[$data['module_id']] = $location . "/$controller.php";
            }
        }
        $admin = 'core2/mod/admin/ModAdminApi.php';
        require_once $admin;
        $this->_apis[] = $admin;
        define("SERVER", (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . DOC_PATH);

        $openapi = \OpenApi\Generator::scan($this->_apis,
            ['exclude' => ['vendor'], 'pattern' => '*.php']
        );

        header('Content-Type: application/json');
        echo $openapi->toJson();
        return "";
    }


    /**
     * @return array
     * @throws \Exception
     */
    public function getSections(): array {

        $sections = [
            [ 'name' => 'core2', 'title' => 'Core2', ]
        ];

        $mods = $this->dataModules->getModuleList();

        foreach ($mods as $mod) {

            if (isset($sections[$mod['module_id']])) {
                continue;
            }

            $location   = $this->getModuleLocation($mod['module_id']);
            $controller = "Mod" . ucfirst(strtolower($mod['module_id'])) . "Api";

            if (file_exists("{$location}/{$controller}.php") && file_exists("{$location}/Api/schema.json")) {
                $sections[$mod['module_id']] = [
                    'name'  => $mod['module_id'],
                    'title' => trim(strip_tags($mod['m_name']))
                ];
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

        $file_schema = '';

        if ($section == 'core2') {
            $file_schema = __DIR__ . '/../../schema.json';

        } else {
            $mods = $this->dataModules->getModuleList();

            foreach ($mods as $mod) {
                if ($mod['module_id'] == $section) {

                    $location    = $this->getModuleLocation($mod['module_id']);
                    $file_schema = "{$location}/Api/schema.json";
                    break;
                }
            }
        }

        if (file_exists($file_schema)) {
            $schema_content = file_get_contents($file_schema);
            $schema         = json_decode($schema_content, true);

            if ( ! is_array($schema)) {
                return [];
            }

            $current_server = "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}";

            $servers = [
                [ 'url' => $current_server ]
            ];

            if ( ! empty($schema['servers']) && is_array($schema['servers'])) {
                foreach ($schema['servers'] as $server) {
                    if ( ! empty($server['url']) &&
                         ! $current_server != $server['url']
                    ) {
                        $servers[] = $server;
                    }
                }
            }

            $schema['servers'] = $servers;

            return $schema;
        }

        return [];
    }
}
