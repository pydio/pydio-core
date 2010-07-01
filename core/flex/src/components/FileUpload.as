package components
{
	import flash.events.*;
	import flash.net.FileReference;
	import flash.net.URLRequest;
	import flash.net.URLRequestMethod;
	import flash.net.URLVariables;
	
	import mx.containers.*;
	import mx.controls.*;
	import mx.core.ScrollPolicy;

	public class FileUpload extends HBox
	{
		private var bar:ProgressBar;
		private var _file:FileReference;
		private var nameText:Label;
		private var _uploaded:Boolean;
		private var _uploading:Boolean;
		private var _bytesUploaded:uint;
		private var _uploadUrl:String;
		private var button:Button;
		private var _alreadyExists:Boolean;
		private var _renameFlag:Boolean;
        
        public var dir:String = '';
		
		// constructor
		public function FileUpload(file:FileReference,uploadUrl:String,uploaded:String,remove:String,byteText:String,removeIcon:Class)
		{
			super();
			// initialize variables
			_file = file;
			_uploadUrl = uploadUrl;
			_uploaded = false;	
			_uploading = false;
			_bytesUploaded = 0;
			_alreadyExists = false;
			_renameFlag = false;
			
			// set styles
			setStyle("backgroundColor","#eeeeee");
			setStyle("paddingBottom","2");
			setStyle("paddingTop","2");
			setStyle("paddingLeft","7");
			setStyle("verticalAlign", "middle");			
			verticalScrollPolicy = ScrollPolicy.OFF;
			
			// set event listeners
			_file.addEventListener(Event.COMPLETE,OnUploadComplete);
			_file.addEventListener(DataEvent.UPLOAD_COMPLETE_DATA,OnUploadDataComplete);
			_file.addEventListener(ProgressEvent.PROGRESS,OnUploadProgressChanged);
			_file.addEventListener(HTTPStatusEvent.HTTP_STATUS,OnHttpError);
			_file.addEventListener(IOErrorEvent.IO_ERROR,OnIOError);
			_file.addEventListener(SecurityErrorEvent.SECURITY_ERROR,OnSecurityError);
			
			// add controls
			var vbox:VBox = new VBox();	
			vbox.setStyle("paddingLeft","5");		
			vbox.setStyle("verticalGap", "0");
						
			button = new Button();
			button.setStyle("icon", removeIcon);
			button.height = 25;
			button.width = 25;			
			button.label = remove;			
			this.addChild(button);			
			button.addEventListener(MouseEvent.CLICK,OnRemoveButtonClicked);
			
			nameText = new Label();
			nameText.width = 275;		
			nameText.text = _file.name + "-" + FormatSize(_file.size, byteText);			
			vbox.addChild(nameText);
			
			
			bar = new ProgressBar();
			bar.mode = ProgressBarMode.MANUAL;
			bar.label = uploaded + " 0%";
			bar.labelPlacement = "right";
			bar.width = 275;	
			vbox.addChild(bar);
			
			this.addChild(vbox);
								
		}
		private function OnRemoveButtonClicked(event:Event):void{
			if(_uploading)
				_file.cancel();
			this.dispatchEvent(new FileUploadEvent(this,"FileRemoved"));
		}
		
		private function OnUploadComplete(event:Event):void{
			_uploading = false;
			_uploaded = true;
			this.dispatchEvent(new FileUploadEvent(this,"UploadComplete"));
		}
		
		private function OnUploadDataComplete(event:DataEvent):void{
			this.dispatchEvent(event);
		}
		
		private function OnHttpError(event:HTTPStatusEvent):void{
			this.dispatchEvent(event);
		}
		
		private function OnIOError(event:IOErrorEvent):void{
			this.dispatchEvent(event);
		}
		
		private function OnSecurityError(event:SecurityErrorEvent):void{
			this.dispatchEvent(event);
		}
		
		// handles the progress change of the upload
		private function OnUploadProgressChanged(event:ProgressEvent):void{
			var bytesUploaded:uint = event.bytesLoaded - _bytesUploaded;
			_bytesUploaded = event.bytesLoaded;
			bar.setProgress(event.bytesLoaded,event.bytesTotal);
			bar.label = "Uploaded " + FormatPercent(bar.percentComplete) + "%";
			this.dispatchEvent(new FileUploadProgressChangedEvent(this,bytesUploaded,"UploadProgressChanged"));			
		}
		
		// get whether the file is uploading
		public function get IsUploading():Boolean{
			return _uploading;
		}
		
		// get whether the file has been uploaded
		public function get IsUploaded():Boolean{
			return _uploaded;
		}
		
		// get the number of bytes uploaded
		public function get BytesUploaded():uint{
			return _bytesUploaded;
		}
		
		// get the upload url
		public function get UploadUrl():String{
			return _uploadUrl;
		}
		
		// set the upload url
		public function set UploadUrl(uploadUrl:String):void{
			_uploadUrl = uploadUrl;
		}
		
		public function setAlreadyExists():void{
			_alreadyExists = true;
		}
		
		public function alreadyExists():Boolean{
			return _alreadyExists;
		}
		
		public function setRename():void{
			this._renameFlag = true;
		}
		
		// gets the size of the file
		public function get FileSize():uint{
			var size:uint = 0;
			try{
				size = _file.size;
			}
			catch (err:Error) {
				size = 0;
			}
			return size;
		}
		
		public function getFileName():String{
			var name:String = "";
			try{
				name = _file.name;
			}catch(err:Error){
				name = "";
			}
			return name;
		}
		
		// upload the file
		public function Upload():void{
			_uploading = true;
			_bytesUploaded = 0;
			if(this._renameFlag){
				_uploadUrl += '&auto_rename=true';
			}
            var request:URLRequest = new URLRequest(_uploadUrl);
            request.method = URLRequestMethod.POST;
            var data:URLVariables = new URLVariables();
            data.dir = dir;
            request.data = data;
			_file.upload(request, "Filedata", true);
		}
		
		// cancels the upload of a file
		public function CancelUpload():void{
			_uploading = false;
			_file.cancel();
		}
		
		// helper function to format the file size
		public static function FormatSize(size:uint, byteText:String):String{
			if(size < 1024)
		        return PadSize(int(size*100)/100) + " " + byteText;
		    if(size < 1048576)
		        return PadSize(int((size / 1024)*100)/100) + "KB";
		    if(size < 1073741824)
		       return PadSize(int((size / 1048576)*100)/100) + "MB";
		     return PadSize(int((size / 1073741824)*100)/100) + "GB";
		}
		
		// helper function to format the percent
		public static function FormatPercent(percent:Number):String{
			percent = int(percent);
			return String(percent);
		}
		
		// helper function to pad the right side of the file size
		public static function PadSize(size:Number):String{
			var temp:String = String(size);
			var index:int = temp.lastIndexOf(".");
			if(index == -1)
				return temp;// + ".00";
			else if(index == temp.length - 2)
				return temp + "0";
			else
				return temp;
		}
		
	}
}