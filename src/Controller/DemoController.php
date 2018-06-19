<?php
namespace RestApi\Controller;

use RestApi\Controller\AppController;
use Cake\Core\Configure;

/**
 * DemoController class
 *
 * PHP version 5.5
 *
 * @author   Dilshad Khan <dilshad.khan@mind2minds.com>
 * @license  https://github.com/mind2minds/cakephp-rest-api/blob/master/LICENSE
 * @link     https://github.com/mind2minds/cakephp-rest-api
 */
class DemoController extends AppController
{
    /**
     * Read contacts or single contact details when id given
     */
    public function contacts($id = null)
    {
        $contacts = [
            '1' => ['name' => 'Glen Schuster', 'email' => 'Glen.Schuster55@yahoo.com'],
            '2' => ['name' => 'Major Lubowitz', 'email' => 'Major.Lubowitz9@gmail.com'],
            '3' => ['name' => 'Braulio Frami', 'email' => 'Braulio21@gmail.com'],
            '4' => ['name' => 'Brisa Mann', 'email' => 'Brisa.Mann@yahoo.com'],
            '5' => ['name' => 'Jalyn Emard', 'email' => 'Jalyn_Emard@hotmail.com']
        ];
        $result = [];
        if(!empty($id)) {
            if (empty($contacts[$id])) {
                $this->_error(404, 'Missing Contacts', 'Invalid Id Supplied');
            } else {
                $contact = $contacts[$id];
                $result = [
                    'Id' => $id,
                    'type' => 'Contact',
                    '_name' => $contact['name'],
                    '_email' => $contact['email']
                ];
            }
        } else {
            foreach ($contacts as $id => $contact) {
                $result[] = [
                    'Id' => $id,
                    'type' => 'Contact',
                    '_name' => $contact['name'],
                    '_email' => $contact['email']
                ];
            }
        }
        $this->_createJsonApiResponse($result);
    }

}