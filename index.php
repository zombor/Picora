<?php

/**
 * Picora™ : PHP Micro Framework
 * http://livepipe.net/projects/picora/
 * 
 * Copyright (c) 2007 LivePipe LLC
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 *
 * @author Ryan Johnson <ryan@syntacticx.com>
 * @copyright 2007 LivePipe LLC
 * @license MIT
 */

$path = dirname(__FILE__);
require_once($path.'/classes/PicoraSupport.php');
require_once($path.'/classes/PicoraEvent.php');
require_once($path.'/classes/PicoraAutoLoader.php');
require_once($path.'/classes/PicoraDispatcher.php');
require_once($path.'/classes/PicoraController.php');
require_once($path.'/classes/PicoraView.php');
require_once($path.'/config.php');
require_once($path.'/functions.php');
PicoraAutoLoader::addFolder($path.'/classes/');
PicoraAutoLoader::addFolder($path.'/controllers/');
PicoraAutoLoader::addFolder($path.'/models/');
if(defined('CONNECTION_STRING'))
	PicoraActiveRecord::connect(CONNECTION_STRING);
print PicoraDispatcher::dispatch($path,BASE_URL,(isset($_GET['__route__']) ? '/'.$_GET['__route__'] : '/'));

?>