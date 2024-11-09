import type { ErrorListProps } from './types';

export const ErrorList = ({ errors }: ErrorListProps) => {
    if (!errors || errors.length < 0) {
        return null;
    }
    return (
        <ul className="tw-text-red-500 tw-text-xs tw-mt-1">
            {errors.map((error, i) => (
                <li key={i}>{error}</li>
            ))}
        </ul>
    );
};
