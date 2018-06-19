<?php
namespace RestApi\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;

/**
 * JsonApi component
 */
class JsonApiComponent extends Component
{

    /**
     * Recursively converts properties of the given input array to a
     * specific JSON API structure. (Fields starting with _ are placed
     * into the ['attributes'] sub-array
     *
     * @param array $input the input array
     *
     * @return array
     */
    public function splitRec($input)
    {
        // If the value is an object, convert it to array
        if (is_object($input)) {
            $input = json_decode(json_encode($input), true);
        }

        if (!is_array($input) || empty($input)) {
            return null;
        }

        if (is_array($input)) {
            foreach ($input as $index => $row) {
                if (is_array($row) || is_object($row)) {
                    if ($index[0] == '_') {
                        $input['attributes'][substr($index, 1)] = $this->splitRec($row);
                        unset($input[$index]);
                    } else {
                        $input[$index] = $this->splitRec($row);
                    }
                } else {
                    if (strtolower($index) == 'id') {
                        $input['id'] = $row;
                        unset($input[$index]);
                    } elseif ($index[0] == '_') {
                        $input['attributes'][substr($index, 1)] = $row;
                        unset($input[$index]);
                    }
                }
            }
        }

        return $input;
    }
}
