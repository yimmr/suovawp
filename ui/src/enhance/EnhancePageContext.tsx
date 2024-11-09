import { createContext, useContext, useState } from 'react';

export interface DataType {
    [k: string]: any;
}

const EnhancePageContext = createContext<DataType>({});

export const useEnhancePageData = () => useContext(EnhancePageContext);

export const EnhancePageProvider = ({
    children,
    initData = {},
}: React.PropsWithChildren<{ initData?: DataType }>) => {
    const [data] = useState<DataType>(initData);
    return <EnhancePageContext.Provider value={data}>{children}</EnhancePageContext.Provider>;
};
