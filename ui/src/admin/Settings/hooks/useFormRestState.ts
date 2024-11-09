import { useCallback, useState } from 'react';
import { useFormReset } from './useFormReset';

export default function <T extends HTMLElement, V>(initialState: V) {
    const [value, setValue] = useState<V>(initialState);
    const handleReset = useCallback(() => setValue(initialState), [initialState]);
    const inputRef = useFormReset<T>(handleReset);
    return [value, setValue, inputRef] as const;
}
