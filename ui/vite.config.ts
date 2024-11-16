import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { fileURLToPath } from 'url';

import dts from 'vite-plugin-dts';
import { libInjectCss } from 'vite-plugin-lib-inject-css';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        react(),
        libInjectCss(),
        dts({
            include: ['src'],
            outDir: 'dist/types',
            tsconfigPath: './tsconfig.app.json',
            staticImport: true,
            insertTypesEntry: true,
        }),
    ],
    build: {
        lib: {
            entry: {
                index: path.resolve(__dirname, 'src/index.ts'),
                enhance: path.resolve(__dirname, 'src/enhance/index.ts'),
                utils: path.resolve(__dirname, 'src/utils/index.ts'),
                admin: path.resolve(__dirname, 'src/admin/index.ts'),
            },
            formats: ['es'],
        },
        cssCodeSplit: true,
        rollupOptions: {
            external: [
                'react',
                'react-dom',
                'react/jsx-runtime',
                '@wordpress/components',
                '@wordpress/i18n',
            ],
            output: {
                globals: {
                    react: 'React',
                    'react-dom': 'ReactDOM',
                },
                dir: 'dist',
                // assetFileNames: 'assets/[name][extname]',
            },
        },
    },
});
