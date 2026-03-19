<?php
namespace Core2;
require_once 'Acl.php';
use OpenApi\Attributes as OA;
use OpenApi\Generator;
use OpenApi\SourceFinder;

#[OA\OpenApi(
    security: [['bearerAuth' => []]]
)]
#[OA\Info(
    version: '2.9.2',
    description: 'Common API',
    title: 'CORE2',
    contact: new OA\Contact(
        name: 'mister easter',
        email: 'easter.by@gmail.com'
    )
)]
//#[OA\Server(url: SERVER)]
#[OA\Components(securitySchemes: [
        new OA\SecurityScheme(
            type: "http",
            securityScheme: "bearerAuth",
            scheme: "bearer",
            in: "header",
            bearerFormat: "JWT"
        ),
        new OA\SecurityScheme(
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

    #[OA\Get(
        path: '/',
        operationId: 'getModules',
        summary: 'Данные для главного меню',
        tags: ['core2'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'информация о пользователе и список доступных модулей',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'system_name', type: 'string', title: 'Название системы'),
                        new OA\Property(property: 'id', type: 'integer', title: 'ID текущего пользователя'),
                        new OA\Property(property: 'name', type: 'string', title: 'Имя текущего пользователя'),
                        new OA\Property(property: 'login', type: 'string', title: 'Login текущего пользователя'),
                        new OA\Property(property: 'avatar', type: 'string', title: 'ссылка на аватар'),
                        new OA\Property(property: 'required_location', type: 'boolean', title: 'должен ли пользователь предоставить данные о местоположении'),
                        new OA\Property(property: 'modules', title: 'System admin', type: 'object'),
                    ]

                ),
            ),
            new OA\Response(
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
        if (!defined("SERVER")) define("SERVER", (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['SERVER_NAME'] . DOC_PATH);

        $openapi   = (new Generator())->generate(new SourceFinder($this->_apis, ['vendor'], '*.php'));

        header('Content-Type: application/json');
        echo $openapi->toJson();
        return "";
    }


}
