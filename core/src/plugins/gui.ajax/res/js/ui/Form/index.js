import HelperMixin from './mixins/HelperMixin'
import Manager from './manager/Manager'
import InputText from './fields/TextField'
import ValidPassword from './fields/ValidPassword'
import InputInteger from './fields/InputInteger'
import InputBoolean from './fields/InputBoolean'
import InputButton from './fields/InputButton'
import MonitoringLabel from './fields/MonitoringLabel'
import InputSelectBox from './fields/InputSelectBox'
import AutocompleteBox from './fields/AutocompleteBox'
import InputImage from './fields/InputImage'
import FormPanel from './panel/FormPanel'
import PydioHelper from './panel/FormHelper'
import FileDropZone from './fields/FileDropzone'
import UserCreationForm from './panel/UserCreationForm'

let PydioForm = {
    HelperMixin,
    Manager,
    InputText,
    ValidPassword,
    InputBoolean,
    InputInteger,
    InputButton,
    MonitoringLabel,
    InputSelectBox,
    AutocompleteBox,
    InputImage,
    FormPanel,
    PydioHelper,
    FileDropZone,
    UserCreationForm,
    createFormElement: Manager.createFormElement,
};

export {PydioForm as default}