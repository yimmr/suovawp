import type { TextFieldProps } from './types';
import InputField from './InputField';

export default ({ type, ...props }: TextFieldProps) => {
    return <InputField {...props} type="input" variant="text" />;
};
