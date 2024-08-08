
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

export const showAlert = (type, title, text, timer = 1500) => {
  MySwal.fire({
    position: 'center',
    icon: type,
    title: title,
    text: text,
    showConfirmButton: false,
    timer: timer || 3000
  });
};
