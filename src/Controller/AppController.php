<?php
/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright   Copyright (c) 2018 Mind2Minds - The Professional Hut
 * @author    Dilshad Khan <dilshad.khan@mind2minds.com>
 * @license   https://github.com/mind2minds/cakephp-rest-api/blob/master/LICENSE MIT License
 */
namespace RestApi\Controller;

use App\Controller\AppController as BaseController;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
/**
 * RestApi AppController class
 *
 * PHP version 5.5
 *
 * @category Cakephp_Plugin
 * @package  RestApi
 * @author   Dilshad Khan <dilshad.khan@mind2minds.com>
 * @license  https://github.com/mind2minds/cakephp-rest-api/blob/master/LICENSE
 * @link     https://github.com/mind2minds/cakephp-rest-api
 */
class AppController extends BaseController
{

    /**
     * Keep the detected apiKey in this class attribute.
     * The apiKey can come from 2 places:
     * - the 'Authorization' header
     *
     * @var null
     */
    protected $apiKey = null;

    /**
     * The language we are requesting the information in
     *
     * @var string
     */
    protected $language = 'ENG';

    /**
     * List of methods that do not require authentication
     *
     * @var array
     */
    protected $openCalls = ['images', 'test'];

    /**
     * The path this API responds from
     *
     * @var string
     */
    protected $selfApiPath = '/api2';

    /**
     * Memorizes the start time of the current call as a float
     *
     * @var float
     */
    protected $startCallTime;


    /**
     * beforeFilter method
     *
     * @param \Cake\Event\Event $event An Event instance
     *
     * @return \Cake\Http\Response|null
     **/
    public function beforeFilter(Event $event)
    {
        $this->startCallTime = microtime(true);

        $this->response->withType('jsonapi');

        // Determine authorization key from the Authorization header
        $this->apiKey = null;
        // Get all headers
        $headers = [];
        foreach ($_SERVER as $key => $item) {
            if (substr($key, 0, 5) == 'HTTP_') {
                $headerName = substr($key, 5);
                $headerName = str_replace('_', '-', $headerName);
                $headers[ucwords(strtolower($headerName), '-')] = $item;
            }
        }

        if (!empty($headers['Authorization'])) {
            $authorization = $headers['Authorization'];
            $authorization = explode(' ', $authorization);
            if (count($authorization) == 2 && $authorization[0] == 'Basic') {
                $this->apiKey = base64_decode($authorization[1]);
            }
        }

        if (in_array($this->request->getParam('action'), $this->openCalls)) {
            return null;
        }

        $this->RequestHandler->ext = 'json';

        if (!$this->request->is(['get', 'post', 'put', 'delete'])) {
            $this->autoRender = false;

            echo json_encode([
                'errors' => [
                    'status' => 401,
                    'title' => 'Invalid request method',
                    'detail' => 'Only GET, POST, PUT and DELETE are accepted.'
                    ]
                ]);
            $this->response->withStatus(401);
            exit(0);
        }

        // validate API key coming from Basic Auth header
        $this->_validateRequest();

        //TODO:
        // Remove comments below code lines if limit/required/rate the api requests with specified api key.
        // It will work with ApiKeys model

        // $limitResponse = $this->ApiKeys->checkLimits($this->apiKey);
        // $this->response->withHeader('X-Rate-Limit-Limit', $limitResponse['totalRequests']);
        // $this->response->withHeader('X-Rate-Limit-Remaining', $limitResponse['remainingRequests']);
        // $this->response->withHeader('X-Rate-Limit-Reset', $limitResponse['remainingTime']);
        // if (!$limitResponse['result']) {
        //     echo json_encode(
        //         [
        //             'errors' => [
        //                 'status' => 429,
        //                 'title' => 'Too many requests',
        //                 'detail' => 'Too many requests have been done in the given period'
        //             ]
        //         ]
        //     );

        //     $this->response->withStatus(429);
        //     exit(0);
        // }

        $this->determineLanguage();

        return parent::beforeFilter($event);
    }

    /**
     * Validate API key fetched from Http Auth header
     *
     * @return void
    */
    private function _validateRequest()
    {
        // API keys data fetched from config for demo purpose
        // It can be a Model e.g. ApiKeys or any other data source
        // e.g. model implementation
        // $this->loadModel('ApiKeys');
        // to validate apiKey
        // $this->ApiKeys->checkKey($this->apiKey)
        $apiKeys = Configure::read('RESTAPI.APIKEYS');
        if (empty($apiKeys)) {
            $apiKeys = [];
        }

        if (is_null($this->apiKey) || !(in_array($this->apiKey, $apiKeys))) {
            $this->autoRender = false;
            echo json_encode(
                [
                    'errors' => [
                        'status' => 401,
                        'title' => 'Missing or invalid key',
                        'detail' => 'Security key is either missing or is invalid'
                    ]
                ]
            );

            $this->response->withStatus(401);
            exit(0);
        }
    }

    /**
     * beforeRender method
     *
     * @param \Cake\Event\Event $event An Event instance
     * @return void
     **/
    public function beforeRender(Event $event)
    {
        parent::beforeRender($event);

        $this->set('_serialize', true);

        // If we are dealing with a JSON API, do not escape slashes
        if (array_key_exists('_serialize', $this->viewVars)
            && in_array($this->response->getType(), ['application/vnd.api+json', 'application/json', 'application/xml'])
        ) {
            $this->set('_jsonOptions', JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }

    /**
     * Splits the information received from the PackageManager into JSONAPI-compatible
     * components. Adds ['links'] and ['data'] sub-arrays
     *
     * @param array $data     The input data
     * @param array $included Any included resources should be in this array
     * @param array $pageObj  This array contains [totalPages,firstPage,prevPage,nextPage,lastPage] calculated via _createPagingLinks()
     *
     * @return void
     **/
    protected function _createJsonApiResponse($data, $included = [], $pageObj = null)
    {
        $this->loadComponent('Api2.JsonApi');

        if (empty($data)) {
            $data = [];
        }

        $response = [];
        if (is_array($included) && !empty($included)) {
            $response['included'] = $included;
        }

        $encoding = Configure::read('App.encoding');

        $response = array_merge($response, [
            'links' => [
                'self' => $_SERVER['REQUEST_URI']
            ],
            'data' => $data,
            'meta' => [
                'copyright' => 'Copyright (c) ' . (date('Y') - 1) . '-' . date('Y') . ' Mind2Minds - The Professional Hut',
                'generated' => date('c'),
                'time' => round(microtime(true) - $this->startCallTime, 4) * 1000,
                'authors' => [
                    'Dilshad Khan'
                ],
                'language' => $this->language,
                'encoding' => $encoding
            ],
            'jsonapi' => [
                'version' => "1.0"
            ]
        ]);

        if (!empty($this->request->getQuery('sort'))) {
            $response['meta']['sort'] = $this->request->getQuery('sort');
        }

        //Set the Locale info in meta
        if (!empty($this->request->getQuery('locale'))) {
            $response['meta']['locale'] = $this->request->getQuery('locale');
        } else {
            //Set default Locale if no locale is passed by User
            $response['meta']['locale'] = 'en-US';
        }

        //add plus sign to sort fields to indicate the sort direction
        if (isset($response['meta']['sort'])) {
            $sort = explode(",", $response['meta']['sort']);
            foreach ($sort as $key => $value) {
                if (strpos($value, '-') === false) {
                    $sort[$key] = "+$value";
                }
            }
            $response['meta']['sort'] = implode(", ", $sort);
        }

        if ($pageObj !== null) {
            $response['meta']['totalPages'] = $pageObj['totalPages'];
            $response['links']['first'] = $pageObj['firstPage'];
            $response['links']['prev'] = $pageObj['prevPage'];
            $response['links']['next'] = $pageObj['nextPage'];
            $response['links']['last'] = $pageObj['lastPage'];
        }

        $response = $this->JsonApi->splitRec($response);

        foreach ($response as $name => $value) {
            $this->set($name, $value);
        }
    }

    /**
     * Creates paging links according to JSONAPI
     *
     * @param string $modelName The name of the model
     * @param string $action The name of the route->action
     * @param int $totalCount The totalCount of the conditioned fetched records
     * @return array $pageObj This array contains [totalPages,firstPage,prevPage,nextPage,lastPage]
     **/
    protected function _createPagingLinks($modelName, $action, $totalCount, $pageOptions = null)
    {
        $pageObj = [
                    'totalPages' => 0,
                    'firstPage' => null,
                    'prevPage' => null,
                    'nextPage' => null,
                    'lastPage' => null
                    ];
        if ($totalCount <= 0) {
            return $pageObj;
        }
        if ($this->{$modelName}->behaviors()->has('Filter') || $pageOptions!=null) {
            if ($pageOptions!=null) {
                $queryPageObj = $pageOptions;
            } else {
                $queryPageObj = $this->{$modelName}->behaviors()->get('Filter')->getPageOptions();
            }

            $page = $queryPageObj['offset'];
            $limit = $queryPageObj['limit'];
            $pageCount = (int)ceil($totalCount / $limit);
            $page = max(min($page, $pageCount), 1);

            $queryParams = $this->request->getQuery();
            unset($queryParams['page']);

            $baseUrl = $this->selfApiPath . "/" . $action;

            if (!empty($this->request->pass)) {
                $baseUrl .= explode("/", $this->request->pass);
            }

            if (!empty($queryParams)) {
                $baseUrl .= "?" . urldecode(http_build_query($queryParams));
            } else {
                $baseUrl .= "?";
            }

            if ((int)$queryPageObj['offset'] > 1) {
                $pageObj['firstPage'] = $baseUrl . "&page[offset]=1&page[limit]=" . $limit;
            }
            if ($page > 1) {
                $pageObj['prevPage'] = $baseUrl . "&page[offset]=" . ($page - 1) . "&page[limit]=" . $limit;
            }

            if ($totalCount > ($page * $limit)) {
                $pageObj['nextPage'] = $baseUrl . "&page[offset]=" . ( $page + 1) . "&page[limit]=" . $limit;
            }

            if ($page != $pageCount) {
                $pageObj['lastPage'] = $baseUrl . "&page[offset]=" . $pageCount . "&page[limit]=" . $limit;
            }

            $pageObj['totalPages'] = $pageCount;
        }

        return $pageObj;
    }
    /**
     * Determines language based on request and sets $this->_language with
     * the corresponding ISO 639-2/T string
     *
     * @return void
     */
    protected function determineLanguage()
    {
        $languages = [
            'de-DE' => 'ALE',
            'es-ES' => 'CAS',
            'ca-ES' => 'CAT',
            'zh-CN' => 'CHI',
            'zh-TW' => 'CHI',
            'da-DK' => 'DAN',
            'en-US' => 'ENG',
            'fi-FI' => 'FIN',
            'fr-FR' => 'FRA',
            'el-GR' => 'GRE',
            'nl-NL' => 'HOL',
            'hu-HU' => 'HUN',
            'hi-IN' => 'IND',
            'it-IT' => 'ITA',
            'ja-JP' => 'JPN',
            'ko-KR' => 'KOR',
            'ms-MY' => 'MAL',
            'nb-NO' => 'NOR',
            'pl-PL' => 'POL',
            'pt-PT' => 'POR',
            'ru-RU' => 'RUS',
            'sv-SE' => 'SUE',
            'th-TH' => 'TAI',
            'tr-TR' => 'TUR'
        ];

        // Determine language from 'locale' query parameter
        if (!empty($this->request->getQuery('locale'))) {
            if (!empty($languages[$this->request->getQuery('locale')])) {
                $this->_language = $languages[$this->request->getQuery('locale')];
            }
        }

        // Just in case the headers contain 'Accept-Language', use this
        if (empty($this->_language) && !empty($this->request->getHeader('Accept-Language'))) {
            $requestedLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);

            $found = false;
            foreach ($requestedLanguages as $language) {
                $components = explode(';', $language);

                if (!empty($components[0])) {
                    $setLanguage = $components[0];

                    if (!empty($languages[$setLanguage])) {
                        $this->_language = $languages[$setLanguage];
                        $found = true;
                    }
                }

                if ($found) {
                    break;
                }
            }
        }

        // In case everything failed, use default 'ENG' language
        if (empty($this->_language)) {
            $this->_language = 'ENG';
        }
    }

    /**
     * Initialize method
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
    }
}
