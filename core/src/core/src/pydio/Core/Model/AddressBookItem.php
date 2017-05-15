<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com/>.
 */
namespace Pydio\Core\Model;

use Pydio\Core\Http\Response\XMLSerializableResponseChunk;
use Pydio\Core\Utils\Vars\StringHelper;

defined('AJXP_EXEC') or die('Access not allowed');


/**
 * Class PublicUserOrGroup
 * Small container for publishing users or group data
 * @package Pydio\Core\Model
 */
class AddressBookItem implements \JsonSerializable, XMLSerializableResponseChunk {

    private $data = [];

    /**
     * PublicUserOrGroup constructor.
     * @param $type
     * @param $id
     * @param $label
     * @param null $avatar
     * @param bool $temporary
     * @param bool $external
     */
    function __construct($type, $id, $label, $temporary = false, $external = false, $avatar = null){
        $this->data = [
            'type'          => $type,
            'id'            => $id,
            'label'         => $label,
            'temporary'     => $temporary,
            'external'      => $external,
            'avatar'        => $avatar
        ];
    }

    public function getId(){
        return $this->data['id'];
    }

    public function appendData($key, $data){
        $this->data[$key] = $data;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    function jsonSerialize(){
        return $this->data;
    }

    /**
     * @return string
     */
    public function toXML(){
        $type = $this->data['type'];

        $id = StringHelper::xmlEntities($this->data['id']);
        $label = StringHelper::xmlEntities($this->data['label']);
        $groupIdString = $type === 'group' ? "data-group='$id'":"";
        $temporaryString = $this->data['temporary'] ? "data-temporary='true'" : "";
        $externalString = $this->data['external'] ? "data-external='true'" : "";
        $avatarString = $this->data['avatar'] ? "data-avatar='".StringHelper::xmlEntities($this->data['avatar'])."'" : "";

        return "<li 
            class='complete_".$type."_entry' 
            data-label='$label' 
            data-entry_id='$id' 
            $groupIdString $temporaryString $externalString $avatarString
            ><span class='user_entry_label'>$label</span></li>";

    }
}
