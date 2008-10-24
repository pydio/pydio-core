package components
{
	// Code behind for FlashFileUpload.mxml
	import flash.events.*;
	import flash.external.*;
	import flash.net.FileFilter;
	import flash.net.FileReferenceList;
	import flash.net.URLRequest;
	import flash.net.navigateToURL;
	
	import mx.containers.*;
	import mx.controls.*;
	import mx.core.Application;
	import mx.events.CloseEvent;
	import mx.events.FlexEvent;
	

	public class ApplicationClass extends Application
	{
		private var fileRefList:FileReferenceList;
		private var fileRefListener:Object;
		private var _totalSize:Number;
		private var _uploadedBytes:Number;
		private var _currentUpload:FileUpload;
		private var _uploadFileSize:Number;
		private var _totalUploadSize:Number;
		private var _fileMaxNumber:Number;
		private var _fileTypeDescription:String;
		private var _fileTypes:String;
		private var _currentFolderFiles:Array;
		
		// all controls in the mxml file must be public variables in the code behind
		public var fileContainer:VBox;
		public var fileUploadBox:VBox;
		public var uploadStats:VBox;
		public var totalFiles:Text;
		public var totalSize:Text;
		public var totalText:Text;
		public var sizeText:Text;
		public var totalProgressBar:ProgressBar;
		public var browseButton:Button;
		public var clearButton:Button;
		public var uploadButton:Button;
		public var cancelButton:Button;
		public var limitsText:Text;		
		
		
		// constructor
		public function ApplicationClass()
		{
			super();
			addEventListener (FlexEvent.CREATION_COMPLETE, OnLoad);
			
			
		}
		
		private function OnLoad(event:Event):void{
			// instantiate and initialize variables
			fileRefList = new FileReferenceList();
			_totalSize = 0;
			_uploadedBytes = 0;			
			
			// hook up our event listeners
			fileRefList.addEventListener(Event.SELECT,OnSelect);
			browseButton.addEventListener(MouseEvent.CLICK,OnAddFilesClicked);
			clearButton.addEventListener(MouseEvent.CLICK,OnClearFilesClicked);
			uploadButton.addEventListener(MouseEvent.CLICK,OnUploadFilesClicked);
			cancelButton.addEventListener(MouseEvent.CLICK,OnCancelClicked);
			
			var limitString:String = GetTextFor("UploadLimitsTitle")+"<br>";
			var limitSet:Boolean = false;
			var temp:String = Application.application.parameters.fileSizeLimit;
			if(temp != null && temp != ""){
			    _uploadFileSize = new Number(temp);
			    if(_uploadFileSize != 0){
				    limitString = limitString + GetTextFor("UploadLimitsSizePerFile")+": " + FileUpload.FormatSize(_uploadFileSize, "B") + "<br>";
				    limitSet = true;
				}
			}else
			    _uploadFileSize = 0;
			    
			temp = Application.application.parameters.totalUploadSize;
			if(temp != null && temp != ""){
			    _totalUploadSize = new Number(temp);
			    if(_totalUploadSize != 0){
				    limitString = limitString + GetTextFor("UploadLimitsTotalSize")+": " + FileUpload.FormatSize(_totalUploadSize, "B") + "<br>";
				    limitSet = true;
			    }
			}else
			    _totalUploadSize = 0;
			 
			temp = Application.application.parameters.maxFileNumber;
			if(temp != null && temp != ""){
			    _fileMaxNumber = new Number(temp);
			    if(_fileMaxNumber != 0){
				    limitString = limitString + GetTextFor("UploadLimitsFilesNumber")+": " + _fileMaxNumber + "<br>";
				    limitSet = true;
			    }			    
			}else
			    _fileMaxNumber = 0;
			 
			 if(limitSet) limitsText.htmlText = limitString;
			 
			 temp = Application.application.parameters.currentFolderFiles;
			 var tempSep:String = Application.application.parameters.separator;
			 if(temp != null && temp != "" && tempSep != null && tempSep != ""){
			 	_currentFolderFiles = temp.split(tempSep);
			 }else{
			 	_currentFolderFiles = new Array();
			 }
			    
			_fileTypeDescription = Application.application.parameters.fileTypeDescription;
			_fileTypes = Application.application.parameters.fileTypes;
			
			browseButton.label = GetTextFor("Add");
			clearButton.label = GetTextFor("Clear");
			uploadButton.label = GetTextFor("Upload");
			cancelButton.label = GetTextFor("Cancel");
			totalText.text = GetTextFor("TotalFile");
			sizeText.text = GetTextFor("SizeText");
			
		}
		
		// brings up file browse dialog when add file button is pressed
		private function OnAddFilesClicked(event:Event):void{
		
			if(_fileTypes != null && _fileTypes != "")	
			{
			    if(_fileTypeDescription == null || _fileTypeDescription == "")
			        _fileTypeDescription = _fileTypes;
			        
			    var filter:FileFilter = new FileFilter(_fileTypeDescription, _fileTypes);
                
                fileRefList.browse([filter]);
			}
			else
			    fileRefList.browse();
			
		}
		
		// fires when the clear files button is clicked
		private function OnClearFilesClicked(event:Event):void{			
			// cancels an upload if there is a file being uploaded
			if(_currentUpload != null)
				_currentUpload.CancelUpload();
			// clears all the files
			fileUploadBox.removeAllChildren();
			// reset the labels
			SetLabels();
			// reinitialize the variables;
			_uploadedBytes = 0;
			_totalSize = 0;
			_currentUpload == null;
		}
		
		// fires when the upload upload button is clicked
		private function OnUploadFilesClicked(event:Event):void{
			// get all the files to upload
			var fileUploadArray:Array = fileUploadBox.getChildren();
			// initialize a helper boolean variable
			var fileUploading:Boolean = false;			
			_currentUpload = null;							
			
			// set the button visibility
			uploadButton.enabled = false;
			browseButton.enabled = false;
			clearButton.enabled = false;
			cancelButton.enabled = true;
			// go through the files to check if they have been uploaded and get the first that hasn't
			for(var x:uint=0;x<fileUploadArray.length;x++)
			{
				// find a file that hasn't been uploaded and start it
				if(!FileUpload(fileUploadArray[x]).IsUploaded)
				{
					fileUploading = true;
					// set the current upload and start the upload
					_currentUpload = FileUpload(fileUploadArray[x]);
					_currentUpload.Upload();
					break;
				}
			}	
			// if all files have been uploaded
			if(!fileUploading)
			{
				OnCancelClicked(null);
				// get the javascript complete funtion to call
				var completeFunction:String = Application.application.parameters.completeFunction;
				// if a complete function is passed in, set in flashvars
				if(completeFunction != null && completeFunction != "")							
					navigateToURL(new URLRequest("javascript:"+completeFunction),"_self");
			}
		}
		
		// fired when the cancel button is clicked
		private function OnCancelClicked(event:Event):void{
			// if there is a file being uploaded then cancel it and adjust the uploaded bytes variable to reflect the cancel
			if(_currentUpload != null)
			{
				_currentUpload.CancelUpload();
				_uploadedBytes -= _currentUpload.BytesUploaded;
				_currentUpload = null;					
			}
			// reset the labels and set the button visibility
			SetLabels();
			uploadButton.enabled = true;
			browseButton.enabled = true;
			clearButton.enabled = true;
			cancelButton.enabled = false;
		}
		
		// fired when files have been selected in the file browse dialog
		private function OnSelect(event:Event):void{
			// get the page to upload to, set in flashvars
			var uploadPage:String = Application.application.parameters.uploadPage;
			
			var tempSize:Number = _totalSize;
			
			var fileUploadArray:Array = fileUploadBox.getChildren();			
			
			var tempNumber:Number = fileUploadArray.length;
			var foundExisting:Boolean = false;
			
			// add each file that was selected
			for(var i:uint=0;i<fileRefList.fileList.length;i++)
			{
				// Check the file is not already in the list!
				var fileName:String = String(fileRefList.fileList[i].name).toLowerCase();
				var foundSame:Boolean = false;
				for(var j:uint=0;j<fileUploadArray.length;j++){
					if(fileName == FileUpload(fileUploadArray[j]).getFileName().toLowerCase() ) {
						foundSame = true;
						break;					
					}
				}
				if(foundSame) {
					continue;
				}
				var localFoundExisting:Boolean = false;
				for(j=0;j<_currentFolderFiles.length;j++){
					if(fileName == String(_currentFolderFiles[j]).toLocaleLowerCase()){
						foundExisting = true;
						localFoundExisting = true;
						break;
					}
				}				
				
				// create new FileUpload and add handlers then add it to the fileuploadbox
				if(_uploadFileSize > 0 && fileRefList.fileList[i].size > _uploadFileSize){
				    OnFileSizeLimitReached(fileRefList.fileList[i].name);
				    continue;
				}
				if(_totalUploadSize > 0 && tempSize + fileRefList.fileList[i].size > _totalUploadSize)
				{
				    OnTotalFileSizeLimitReached();
				    break;
				}
				
				if(_fileMaxNumber > 0 && tempNumber + 1 > _fileMaxNumber){
					OnFileMaxNumberReached();
					break;
				}
				
				if((_uploadFileSize == 0 || fileRefList.fileList[i].size < _uploadFileSize) && (_totalUploadSize == 0 || tempSize + fileRefList.fileList[i].size < _totalUploadSize))
				{
				    var fu:FileUpload = new FileUpload(fileRefList.fileList[i],uploadPage, GetTextFor("Uploaded"), GetTextFor("Remove"), GetTextFor("Byte"), this['RemoveIcon']);					
				    fu.percentWidth = 100;				
				    fu.addEventListener("FileRemoved",OnFileRemoved);	
				    fu.addEventListener("UploadComplete",OnFileUploadComplete);
				    fu.addEventListener("UploadProgressChanged",OnFileUploadProgressChanged);
				    fu.addEventListener(HTTPStatusEvent.HTTP_STATUS,OnHttpError);
				    fu.addEventListener(IOErrorEvent.IO_ERROR,OnIOError);
				    fu.addEventListener(SecurityErrorEvent.SECURITY_ERROR,OnSecurityError);
				    if(localFoundExisting){
				    	fu.setAlreadyExists();
				    }
				    fileUploadBox.addChild(fu);	
				    tempSize += fileRefList.fileList[i].size;
				    tempNumber ++;	
				}			
			}
			if(foundExisting){
				OnFileAlreadyExistingFound();
			}
			
			// reset labels
			SetLabels();
		}
		
		// fired when a the remove file button is clicked
		private function OnFileRemoved(event:FileUploadEvent):void{
			_uploadedBytes -= FileUpload(event.Sender).BytesUploaded;
			fileUploadBox.removeChild(FileUpload(event.Sender));				
			SetLabels();
			if(_currentUpload == FileUpload(event.Sender))
				OnUploadFilesClicked(null);
		}
		
		// fired when a file has finished uploading
		private function OnFileUploadComplete(event:FileUploadEvent):void{
			_currentUpload == null;
			OnUploadFilesClicked(null);
		}
		
		private function GetTextFor(text:String):String{
		    if (text == "MaxFilesSizeLimit")
				return Application.application.parameters.maxFileSizeLimitText == undefined ? "The total file size limit has been reached." : Application.application.parameters.maxFileSizeLimitText;
			else if (text == "MaxFileSize")
				return Application.application.parameters.maxFileSizeText == undefined ? "The file is too large and will not be added" : Application.application.parameters.maxFileSizeText;
			else if (text == "MaxFilesNumber")
				return Application.application.parameters.maxFilesNumber == undefined ? "The total file size number has been reached" : Application.application.parameters.maxFilesNumber;
			else if (text == "HTTPError")
				return Application.application.parameters.HTTPErrorText == undefined ? "There has been an HTTP Error: status code " : Application.application.parameters.HTTPErrorText;
			else if (text == "IOError")
				return Application.application.parameters.IOErrorText == undefined ? "There has been an IO Error: " : Application.application.parameters.IOErrorText;
			else if (text == "SecurityError")
				return Application.application.parameters.securityErrorText == undefined ? "There has been a Security Error: " : Application.application.parameters.securityErrorText;
			else if (text == "Uploaded")
				return Application.application.parameters.uploadedText == undefined ? "Uploaded" : Application.application.parameters.uploadedText;
			else if (text == "Remove")
				return Application.application.parameters.removeText == undefined ? "Remove" : Application.application.parameters.removeText;
			else if (text == "Add")
				return Application.application.parameters.addFilesText == undefined ? "Add files" : Application.application.parameters.addFilesText;
			else if (text == "Clear")
				return Application.application.parameters.clearFilesText == undefined ? "Clear files" : Application.application.parameters.clearFilesText;
			else if (text == "Upload")
				return Application.application.parameters.uploadFilesText == undefined ? "Upload files" : Application.application.parameters.uploadFilesText;
			else if (text == "Cancel")
				return Application.application.parameters.cancelUploadText == undefined ? "Cancel upload" : Application.application.parameters.cancelUploadText;
			else if (text == "TotalFile")
				return Application.application.parameters.totalFilesText == undefined ? "Total Files:" : Application.application.parameters.totalFilesText;
			else if (text == "SizeText")
				return Application.application.parameters.totalSizeText == undefined ? "Total Size:" : Application.application.parameters.totalSizeText;
			else if (text == "Byte")
				return Application.application.parameters.bytesText == undefined ? "bytes" : Application.application.parameters.bytesText;
			else{
				return (Application.application.parameters[text] == undefined ? text : Application.application.parameters[text]);
			}
			return "";
		}
		
		private function OnTotalFileSizeLimitReached():void{
		    Alert.show(GetTextFor("MaxFilesSizeLimit"));
		}
		
		private function OnFileMaxNumberReached():void{
			Alert.show(GetTextFor("MaxFilesNumber"));
		}
		
		private function OnFileSizeLimitReached(fileName:String):void{
		    Alert.show(GetTextFor("MaxFileSize") + " : " + fileName);
		}
		
		private function OnFileAlreadyExistingFound():void{
			Alert.buttonWidth = 100;
			Alert.yesLabel = GetTextFor("overwrite");
			Alert.noLabel = GetTextFor("skip");
			Alert.cancelLabel = GetTextFor("rename");
			Alert.show(GetTextFor("existingFilesFound"), "", 1|2|8, this, alertClickHandler);
		}
		
		private function alertClickHandler(event:CloseEvent):void{
			var fUploads:Array = fileUploadBox.getChildren();
			var i:uint=0;
			var crtFU:FileUpload;
			if(event.detail == Alert.NO){
				// Purge existing files from list!
				for(i=0;i<fUploads.length;i++){
					crtFU = FileUpload(fUploads[i]);
					if(crtFU.alreadyExists()){
						fileUploadBox.removeChild(crtFU);
						SetLabels();
					}
				}
			}else if(event.detail == Alert.CANCEL){
				// Set Rename flag
				for(i=0;i<fUploads.length;i++){
					crtFU = FileUpload(fUploads[i]);
					if(crtFU.alreadyExists()){
						crtFU.setRename();
					}
				}				
			}else if(event.detail == Alert.YES){
				// Do nothing, files will be overwritten
			}
			Alert.yesLabel = "OK";
			Alert.noLabel = "Cancel";
		}
				
		//  error handlers
		private function OnHttpError(event:HTTPStatusEvent):void{
			Alert.show(GetTextFor("HTTPError") + event.status);
		}
		private function OnIOError(event:IOErrorEvent):void{
			Alert.show(GetTextFor("IOError") + event.text);
		}
		
		private function OnSecurityError(event:SecurityErrorEvent):void{
			Alert.show(GetTextFor("SecurityError") + event.text);
		}
		
		// fired when upload progress changes
		private function OnFileUploadProgressChanged(event:FileUploadProgressChangedEvent):void{
			_uploadedBytes += event.BytesUploaded;	
			SetProgressBar();
		}
		
		// sets the progress bar and label
		private function SetProgressBar():void{
			totalProgressBar.setProgress(_uploadedBytes,_totalSize);			
			totalProgressBar.label = GetTextFor("Uploaded") + " " + FileUpload.FormatPercent(totalProgressBar.percentComplete) + "% - " 
				+ FileUpload.FormatSize(_uploadedBytes, GetTextFor("Byte")) + " / " + FileUpload.FormatSize(_totalSize, GetTextFor("Byte"));
		}
		
		// sets the labels
		private function SetLabels():void{
			var fileUploadArray:Array = fileUploadBox.getChildren();
			if(fileUploadArray.length > 0)
			{
				totalFiles.text = String(fileUploadArray.length);
				_totalSize = 0;
				for(var x:uint=0;x<fileUploadArray.length;x++)
				{
					_totalSize += FileUpload(fileUploadArray[x]).FileSize;
				}
				totalSize.text = FileUpload.FormatSize(_totalSize, GetTextFor("Byte"));
				SetProgressBar();
				clearButton.enabled = uploadButton.enabled = totalProgressBar.visible =  uploadStats.visible = true;					
			}
			else
			{
				clearButton.enabled = uploadButton.enabled = totalProgressBar.visible = uploadStats.visible = false;					
			}
		}	
	}
}