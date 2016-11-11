<?php

/** @var NetDesign $this */
$this->RemoveSmartyPlugin();
$this->RemoveEventHandler('Core', 'SmartyPreCompile');
$this->RemovePermission(NetDesign::PERMISSION_SET_CLIENT);
return false;