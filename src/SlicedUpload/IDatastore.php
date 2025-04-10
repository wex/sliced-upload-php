<?php

namespace SlicedUpload;

use SlicedUpload\SlicedUpload\Upload;

interface IDatastore
{
    public function getUpload($uuid): Upload;
    
}