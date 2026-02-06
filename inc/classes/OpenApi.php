<?php
namespace Core2;
require_once 'Acl.php';
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
class OpenApi extends Acl {

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

    public function __construct()
    {
        parent::__construct();
        $this->setupAcl();
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function render(): string {
        $this->module = 'admin';
        $mods     = $this->dataModules->getModuleList();
        foreach ($mods as $k => $data) {
            if (isset($this->_apis[$data['module_id']])) continue;
            if (!$this->checkAcl($data['module_id'])) continue;
            $location      = $this->getModuleLocation($data['module_id']);
            $controller = "Mod" . ucfirst(strtolower($data['module_id'])) . "Api";
            $sources = [];
            if (file_exists($location . "/$controller.php")) {
                require_once $location . "/$controller.php";
                $sources[] = $location . "/$controller.php";
                if (is_dir($location . "/Api")) {
                    $sources[] = $location . "/Api/";
                }
                $this->_apis[$data['module_id']] = $sources;
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


}
