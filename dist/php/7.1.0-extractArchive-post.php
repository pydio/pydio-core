<?php
/*
 * Copyright 2007-2016 Abstrium <contact (at) pydio.com>
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

if(!function_exists('blockAllXHRInPage')){

    function blockAllXHRInPage(){
        print '
        <script type="text/javascript">
            (function(open) {
                parent.XMLHttpRequest.prototype.open = function(method, url, async, user, pass) {
                    console.error("XHR Call to "+url+" blocked by upgrade process!");
                };                                                
            })(parent.XMLHttpRequest.prototype.open);      
        </script>
        <div class="upgrade_result success">Blocking all XHR in page: OK</div>
    ';
    }

}

blockAllXHRInPage();