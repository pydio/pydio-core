// ActionScript file
package components
{
	// custom event
	import flash.events.Event;
	public class FileUploadEvent extends Event{
		private var _sender:Object;
		public function FileUploadEvent(sender:Object,type:String,bubbles:Boolean=false,cancelable:Boolean=false){
			super(type,bubbles,cancelable);
			_sender = sender;
		}
		public function get Sender():Object{
			return _sender;
		}
	}
}