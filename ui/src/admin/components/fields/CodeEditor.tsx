import type { CodeEditorProps } from './types';
import CodeMirror from '@uiw/react-codemirror';
import { loadLanguage } from '@uiw/codemirror-extensions-langs';
import {
    atomone,
    dracula,
    sublime,
    duotoneDark,
    duotoneLight,
    githubDark,
    githubLight,
} from '@uiw/codemirror-themes-all';

const themeMap = {
    light: 'light',
    dark: 'dark',
    github: githubLight,
    'github-light': githubLight,
    'github-dark': githubDark,
    duotone: duotoneLight,
    'duotone-light': duotoneLight,
    'duotone-dark': duotoneDark,
    sublime: sublime,
    atomone: atomone,
    dracula: dracula,
};

export type CodeEditorTheme = keyof typeof themeMap;

const CodeEditor: React.FC<CodeEditorProps> = ({
    value,
    onChange,
    language = 'html',
    theme = 'duotone',
    height = '200px',
    readOnly = false,
}) => {
    return (
        <CodeMirror
            value={value}
            onChange={onChange}
            extensions={[loadLanguage(language)!]}
            theme={(themeMap[theme] as any) ?? 'light'}
            height={height}
            readOnly={readOnly}
            style={{
                border: '1px solid #ddd',
                borderRadius: '4px',
                fontSize: '14px',
            }}
        />
    );
};

export default CodeEditor;
