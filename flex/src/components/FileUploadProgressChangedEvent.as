// ActionScript file
package components
{
	// custom event
	import flash.events.Event;
	public class FileUploadProgressChangedEvent extends FileUploadEvent{
		private var _bytesUploaded:uint;
		public function FileUploadProgressChangedEvent(sender:Object,bytesUploaded:uint,type:String,bubbles:Boolean=false,cancelable:Boolean=false){
			super(sender,type,bubbles,cancelable);
			_bytesUploaded = bytesUploaded;
		}
		public function get BytesUploaded():Object{
			return _bytesUploaded;
		}
	}
}