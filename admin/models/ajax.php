<?php
/*--------------------------------------------------------------------------------------------------------|  www.vdm.io  |------/
    __      __       _     _____                 _                                  _     __  __      _   _               _
    \ \    / /      | |   |  __ \               | |                                | |   |  \/  |    | | | |             | |
     \ \  / /_ _ ___| |_  | |  | | _____   _____| | ___  _ __  _ __ ___   ___ _ __ | |_  | \  / | ___| |_| |__   ___   __| |
      \ \/ / _` / __| __| | |  | |/ _ \ \ / / _ \ |/ _ \| '_ \| '_ ` _ \ / _ \ '_ \| __| | |\/| |/ _ \ __| '_ \ / _ \ / _` |
       \  / (_| \__ \ |_  | |__| |  __/\ V /  __/ | (_) | |_) | | | | | |  __/ | | | |_  | |  | |  __/ |_| | | | (_) | (_| |
        \/ \__,_|___/\__| |_____/ \___| \_/ \___|_|\___/| .__/|_| |_| |_|\___|_| |_|\__| |_|  |_|\___|\__|_| |_|\___/ \__,_|
                                                        | |
                                                        |_|
/-------------------------------------------------------------------------------------------------------------------------------/

	@version		1.0.x
	@build			14th August, 2019
	@created		30th January, 2017
	@package		Questions and Answers
	@subpackage		ajax.php
	@author			Llewellyn van der Merwe <https://www.vdm.io/>
	@copyright		Copyright (C) 2015. All Rights Reserved
	@license		GNU/GPL Version 2 or later - http://www.gnu.org/licenses/gpl-2.0.html

	Questions &amp; Answers

/-----------------------------------------------------------------------------------------------------------------------------*/

// No direct access to this file
defined('_JEXEC') or die('Restricted access');

/**
 * Questionsanswers Ajax Model
 */
class QuestionsanswersModelAjax extends JModelList
{
	protected $app_params;
	
	public function __construct() 
	{		
		parent::__construct();		
		// get params
		$this->app_params	= JComponentHelper::getParams('com_questionsanswers');
		
	}

	// Used in question_and_answer

	protected $viewid = array();

	protected function getViewID($call = 'table')
	{
		if (!isset($this->viewid[$call]))
		{
			// get the vdm key
			$jinput = JFactory::getApplication()->input;
			$vdm = $jinput->get('vdm', null, 'WORD');
			if ($vdm) 
			{
				// set view and id
				if ($view = QuestionsanswersHelper::get($vdm))
				{
					$current = (array) explode('__', $view);
					if (QuestionsanswersHelper::checkString($current[0]) && isset($current[1]) && is_numeric($current[1]))
					{
						// get the view name & id
						$this->viewid[$call] = array(
							'a_id' => (int) $current[1],
							'a_view' => $current[0]
						);
					}
				}
				// set return if found
				if ($return = QuestionsanswersHelper::get($vdm . '__return'))
				{
					if (QuestionsanswersHelper::checkString($return))
					{
						$this->viewid[$call]['a_return'] = $return;
					}
				}
			}
		}
		if (isset($this->viewid[$call]))
		{
			return $this->viewid[$call];
		}
		return false;
	}

	// allowed views
	protected $allowedViews = array('question_and_answer');

	// allowed targets
	protected $targets = array('main', 'answer'); 

	// allowed types
	protected $types = array('image' => 'image', 'images' => 'image', 'document' => 'document', 'documents' => 'document');

	// set some buckets
	protected $target;
	protected $targetType;
	protected $formatType;

	// set some defaults
	protected $formats = 
		array( 
			'image_formats' => array(
				1 => 'jpg',
				2 => 'jpeg',
				3 => 'gif',
				4 => 'png'),
			'document_formats' => array(
				1 => 'doc',
				2 => 'docx',
				3 => 'odt',
				4 => 'pdf',
				5 => 'csv',
				6 => 'xls',
				7 => 'xlsx',
				8 => 'ods',
				9 => 'ppt',
				10 => 'pptx',
				11 => 'pps',
				12 => 'ppsx',
				13 => 'odp',
				14 => 'zip'),
			'media_formats' => array(
				1 => 'mp3',
				2 => 'm4a',
				3 => 'ogg',
				4 => 'wav',
				5 => 'mp4',
				6 => 'm4v',
				7 => 'mov',
				8 => 'wmv',
				9 => 'avi',
				10 => 'mpg',
				11 => 'ogv',
				12 => '3gp',
				13 => '3g2'));

	// file details
	protected $fileName;
	protected $folderPath;
	protected $fullPath;
	protected $fileFormat;
	// return error if upload fails
	protected $errorMessage;
	// set uploading values
	protected $use_streams = false;
	protected $allow_unsafe = false;
	protected $safeFileOptions = array();

	public function uploadfile($target, $type)
	{
		// get the view values
		$view = $this->getViewID();
		if (in_array($target, $this->targets) && isset($this->types[$type]) && isset($view['a_view']) && in_array($view['a_view'], $this->allowedViews))
		{
			$this->target = (string) $target;
			$this->targetType = (string) $type;
			$this->formatType = (string) $this->types[$type];
			if ($package = $this->_getPackageFromUpload())
			{
				// now we move the file into place
				return $this->uploadNow($package, $view);
			}
			return array('error' => $this->errorMessage);
		}
		return array('error' => JText::_('COM_QUESTIONSANSWERS_THERE_HAS_BEEN_AN_ERROR'));
	}

	protected function uploadNow($package, $view)
	{
		// set the package name to file name if found
		$name = $this->formatType;
		if (isset($package['packagename']))
		{
			$name = QuestionsanswersHelper::safeString(str_replace('.'.$this->fileFormat, '', $package['packagename']), 'filename', '_', false);
		}
		$this->fileName = $this->target.'_'.$this->targetType.'_'.$this->fileFormat.'_'.QuestionsanswersHelper::randomkey(20).'VDM'.$name;
		// set the folder path
		if ($this->formatType === 'document' || $this->formatType === 'media')
		{
			// get the folder path
			$this->folderPath = QuestionsanswersHelper::getFolderPath('path', 'hiddenfilepath');
		}
		else
		{
			// get the file path
			$this->folderPath = QuestionsanswersHelper::getFolderPath();
		}
		// set full path to the file
		$this->fullPath = $this->folderPath . $this->fileName . '.' . $this->fileFormat;
		// move to target folder
		if (JFile::move($package['dir'], $this->fullPath))
		{
			// do crop/resize if it is an image and cropping is set
			if ($this->formatType === 'image')
			{
				QuestionsanswersHelper::resizeImage($this->fileName, $this->fileFormat, $this->target, $this->folderPath, $this->fullPath);
			}
			// Get the basic encryption.
			$basickey = QuestionsanswersHelper::getCryptKey('basic');
			$basic = null;
			// set link options
			$linkOptions = QuestionsanswersHelper::getLinkOptions();
			// set link options
			if ($basickey)
			{
				// Get the encryption object.
				$basic = new FOFEncryptAes($basickey, 128);
			}
			// when it is documents we need to give file name in base64
			if ($this->formatType === 'document' || $this->formatType === 'media')
			{
				// store the name
				$keyName = $this->fileName;
				if (QuestionsanswersHelper::checkObject($basic))
				{
					// Get the encryption object.
					$localFile = QuestionsanswersHelper::base64_urlencode($basic->encryptString($keyName));
				}
				else
				{
					// can not get the encryption object so only base64 encode
					$localFile = QuestionsanswersHelper::base64_urlencode($keyName, true);
				}
			}
			// check if we must update the current item
			if (isset($view['a_id']) && $view['a_id'] > 0 && isset($view['a_view']))
			{
				$object = new stdClass();
				$object->id = (int) $view['a_id'];
				if ($this->targetType === 'image' || $this->targetType === 'document')
				{
					if ($linkOptions['lock'] && QuestionsanswersHelper::checkObject($basic))
					{
						// Get the encryption object.
						$object->{$this->target.'_'.$this->targetType} = $basic->encryptString($this->fileName);
					}
					else
					{
						// can not get the encryption object.
						$object->{$this->target.'_'.$this->targetType} = $this->fileName;
					}
				}
				elseif ($this->targetType === 'images' || $this->targetType === 'documents' || $this->targetType === 'media')
				{
					$this->fileName = $this->setFileNameArray('add', $basic, $view);
					if ($linkOptions['lock'] && QuestionsanswersHelper::checkObject($basic))
					{
						// Get the encryption object.
						$object->{$this->target.'_'.$this->targetType} = $basic->encryptString($this->fileName);
					}
					else
					{
						// can not get the encryption object.
						$object->{$this->target.'_'.$this->targetType} = $this->fileName;
					}
					
				}
				JFactory::getDbo()->updateObject('#__questionsanswers_'.$view['a_view'], $object, 'id');
			}
			elseif ($this->targetType === 'images' || $this->targetType === 'documents' || $this->targetType === 'media')
			{
				$this->fileName = array($this->fileName);
				$this->fileName =  '["'.implode('", "', $this->fileName).'"]';
			}
			// set the results
			$result = array('success' =>  $this->fileName, 'fileformat' => $this->fileFormat);
			// add some more values if document format type
			if ($this->formatType === 'document' || $this->formatType === 'media')
			{
				$tokenLink = '';
				if ($linkOptions['lock'] == 0)
				{
					$localFile = QuestionsanswersHelper::base64_urlencode($keyName, true);
				}
				if ($linkOptions['session'])
				{
					$tokenLink = '&token=' . JSession::getFormToken();
				}
				// if document
				if ($this->formatType === 'document')
				{
					$result['link'] = 'index.php?option=com_questionsanswers&task=download.document&file=' . $localFile . $tokenLink;
				}
				// if media
				elseif ($this->formatType === 'media')
				{
					$result['link'] = 'index.php?option=com_questionsanswers&task=download.media&file=' . $localFile . $tokenLink;
				}
				$result['key'] = $keyName;
			}
			return $result;
		}
		$this->remove($package['packagename']);
		return array('error' =>  JText::_('COM_QUESTIONSANSWERS_THERE_HAS_BEEN_AN_ERROR'));
	}

	public function removeFile($oldFile, $target, $clearDB, $type)
	{
		// get view values
		$view = $this->getViewID();
		if (in_array($target, $this->targets) && isset($this->types[$type]) && isset($view['a_view']) && in_array($view['a_view'], $this->allowedViews))
		{
			$this->target = (string) $target;
			$this->targetType = (string) $type;
			$this->formatType = (string) $this->types[$type];
			$this->fileName = (string) $oldFile;
			if (isset($view['a_id']) && $view['a_id'] > 0 && isset($view['a_view']))
			{
				// get user to see if he has permission to upload
				$user = JFactory::getUser();
				if (!$user->authorise($view['a_view'].'.edit.'.$this->target.'_'.$this->targetType, 'com_questionsanswers'))
				{
					return array('error' =>  JText::_('COM_QUESTIONSANSWERS_YOU_DO_NOT_HAVE_PERMISSION_TO_REMOVE_THIS_FILE'));
				}
			}
			if ($this->formatType === 'document' || $this->formatType === 'media')
			{
				// get the file path
				$this->folderPath = QuestionsanswersHelper::getFolderPath('path', 'hiddenfilepath');
			}
			else
			{
				// get the file path
				$this->folderPath = QuestionsanswersHelper::getFolderPath();
			}
			// remove from the db if there is an id
			if ($clearDB == 1 && isset($view['a_id']) && $view['a_id'] > 0 && isset($view['a_view']) && in_array($view['a_view'], $this->allowedViews))
			{
				$object = new stdClass();
				$object->id = (int) $view['a_id'];
				if ($this->targetType === 'image' || $this->targetType === 'document')
				{
					$object->{$this->target.'_'.$this->targetType} = '';
					JFactory::getDbo()->updateObject('#__questionsanswers_'.$view['a_view'], $object, 'id');
				}
				elseif ($this->targetType === 'images' || $this->targetType === 'documents' || $this->targetType === 'media')
				{
					// Get the basic encription.
					$basickey = QuestionsanswersHelper::getCryptKey('basic');
					$basic = null;
					// set link options
					$linkOptions = QuestionsanswersHelper::getLinkOptions();
					if ($linkOptions['lock'] && $basickey)
					{
						// Get the encryption object.
						$basic = new FOFEncryptAes($basickey, 128);
					}
					$fileNameArray = $this->setFileNameArray('remove', $basic, $view);
					if ($linkOptions['lock'] && QuestionsanswersHelper::checkObject($basic))
					{
						// Get the encryption object.
						$object->{$this->target.'_'.$this->targetType} = $basic->encryptString($fileNameArray);
					}
					else
					{
						// can not get the encryption object.
						$object->{$this->target.'_'.$this->targetType} = $fileNameArray;
					}
					JFactory::getDbo()->updateObject('#__questionsanswers_'.$view['a_view'], $object, 'id');
				}
			}
			// load the file class
			jimport('joomla.filesystem.file');
			// remove file with this filename
			$fileFormats = $this->formats[$this->formatType .'_formats'];
			foreach ($fileFormats as $fileFormat)
			{
				if (JFile::exists($this->folderPath . $this->fileName . '.' . $fileFormat))
				{
					// remove the file
					return JFile::delete($this->folderPath . $this->fileName . '.' . $fileFormat);
				}
			}
		}
		return array('error' => JText::_('COM_QUESTIONSANSWERS_THERE_HAS_BEEN_AN_ERROR'));
	}

	protected function setFileNameArray($action, $basic, $view)
	{
		$curentFiles = QuestionsanswersHelper::getVar($view['a_view'], $view['a_id'], 'id', $this->target.'_'.$this->targetType);
		// unlock if needed
		if ($basic && $curentFiles === base64_encode(base64_decode($curentFiles, true)))
		{
			// basic decrypt data banner_image.
			$curentFiles = rtrim($basic->decryptString($curentFiles), "\0");
		}
		// convert to array if needed
		if (QuestionsanswersHelper::checkJson($curentFiles))
		{
			$curentFiles = json_decode($curentFiles, true);
		}
		// remove or add the file name
		if (QuestionsanswersHelper::checkArray($curentFiles))
		{
			if ('add' === $action)
			{
				$curentFiles[] = $this->fileName;
			}
			else
			{
				if(($key = array_search($this->fileName, $curentFiles)) !== false)
				{
					unset($curentFiles[$key]);
				}
			}
		}
		elseif ('add' === $action)
		{
			$curentFiles = array($this->fileName);
		}
		else
		{
			$curentFiles = '';
		}
		// convert to json
		if (QuestionsanswersHelper::checkArray($curentFiles))
		{
			return '["'.implode('", "', $curentFiles).'"]';
		}
		return '';
	}

	/**
	 * Works out an importation file from a HTTP upload
	 *
	 * @return file definition or false on failure
	 */
	protected function _getPackageFromUpload()
	{		
		// Get the uploaded file information
		$app = JFactory::getApplication();
		$input = $app->input;

		// See JInputFiles::get.
		$userfiles = $input->files->get('files', null, 'array');
		
		// Make sure that file uploads are enabled in php
		if (!(bool) ini_get('file_uploads'))
		{
			$this->errorMessage = JText::_('COM_QUESTIONSANSWERS_WARNING_IMPORT_FILE_ERROR');
			return false;
		}

		// get the files from array
		$userfile = null;
		if (is_array($userfiles))
		{
			$userfile = array_values($userfiles)[0]; 
		}

		// If there is no uploaded file, we have a problem...
		if (!is_array($userfile))
		{
			$this->errorMessage = JText::_('COM_QUESTIONSANSWERS_NO_IMPORT_FILE_SELECTED');
			return false;
		}

		// Check if there was a problem uploading the file.
		if ($userfile['error'] || $userfile['size'] < 1)
		{
			$this->errorMessage = JText::_('COM_QUESTIONSANSWERS_WARNING_IMPORT_UPLOAD_ERROR');
			return false;
		}

		// Build the appropriate paths
		$config = JFactory::getConfig();
		$tmp_dest = $config->get('tmp_path') . '/' . $userfile['name'];
		$tmp_src = $userfile['tmp_name'];

		// Move uploaded file
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');
		$p_file = JFile::upload($tmp_src, $tmp_dest, $this->use_streams, $this->allow_unsafe, $this->safeFileOptions);

		// Was the package downloaded?
		if (!$p_file)
		{
			$session = JFactory::getSession();
			$session->clear('package');
			$session->clear('dataType');
			$session->clear('hasPackage');
			$this->errorMessage = JText::_('COM_QUESTIONSANSWERS_COULD_NOT_UPLOAD_THE_FILE');
			// was not uploaded
			return false;
		}

		// check that this is a valid file
		$package = $this->check($userfile['name']);

		return $package;
	}
	
	/**
	 * Check a file and verifies it as a allowed file format file
	 *
	 * @param   string  $archivename  The uploaded package filename or import directory
	 *
	 * @return  array  of elements
	 *
	 */
	protected function check($archivename)
	{
		// Clean the name
		$archivename = JPath::clean($archivename);
		// get file format
		$this->fileFormat = strtolower(pathinfo($archivename, PATHINFO_EXTENSION));
		// get fileFormat key
		$allowedFormats = array();
		if (in_array($this->fileFormat, $this->formats[$this->formatType .'_formats']))
		{
			// get allowed formats
			$allowedFormats = (array) $this->app_params->get($this->formatType.'_formats', null);
		}
		// check the extension
		if (!in_array($this->fileFormat, $allowedFormats))
		{
			// Cleanup the import files
			$this->remove($archivename);
			$this->errorMessage = JText::_('COM_QUESTIONSANSWERS_DOES_NOT_HAVE_A_VALID_FILE_TYPE');
			return false;
		}

		// check permission if user
		$view = $this->getViewID();
		if (isset($view['a_id']) && $view['a_id'] > 0 && isset($view['a_view']) && in_array($view['a_view'], $this->allowedViews))
		{
			// get user to see if he has permission to upload
			$user = JFactory::getUser();
			if (!$user->authorise($view['a_view'].'.edit.'.$this->target.'_'.$this->targetType, 'com_questionsanswers'))
			{
				// Cleanup the import files
				$this->remove($archivename);
				$this->errorMessage = JText::_('COM_QUESTIONSANSWERS_YOU_DO_NOT_HAVE_PERMISSION_TO_UPLOAD_AN'.$this->targetType);
				return false;
			}
		}
		
		$config = JFactory::getConfig();
		// set Package Name
		$check['packagename'] = $archivename;
		
		// set directory
		$check['dir'] = $config->get('tmp_path'). '/' .$archivename;
		
		return $check;
	}
	
	/**
	 * Clean up temporary uploaded file
	 *
	 * @param   string  $package    Name of the uploaded file
	 *
	 * @return  boolean  True on success
	 *
	 */
	protected function remove($package)
	{
		jimport('joomla.filesystem.file');
		
		$config = JFactory::getConfig();
		$package = $config->get('tmp_path'). '/' .$package;

		// Is the package file a valid file?
		if (is_file($package))
		{
			JFile::delete($package);
		}
		elseif (is_file(JPath::clean($package)))
		{
			// It might also be just a base filename
			JFile::delete(JPath::clean($package));
		}
	}
 
}
