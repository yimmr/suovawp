#!/usr/bin/env node

const { readdir, writeFile } = require('fs').promises;
const { join, relative, basename, extname } = require('path');
const { argv } = require('process');

const defaultConfig = {
    scanDir: './app/Layers',
    outputFile: './app/Layers.php',
};

async function findPHPClasses(dir, namespace = '', prefix = '') {
    let results = [];
    const files = await readdir(dir, { withFileTypes: true });
    namespace = namespace ? namespace + '\\' : namespace;
    prefix = prefix ? prefix + '.' : prefix;
    for (const file of files) {
        const { name } = file;
        if (file.isDirectory()) {
            const fullPath = join(dir, name);
            results = results.concat(
                await findPHPClasses(fullPath, namespace + name, prefix + name)
            );
        } else if (name.endsWith('.php')) {
            const leName = basename(name, extname(name));
            const keyName = leName.replace(/([A-Z])/g, '.$1').substring(1);
            const key = (prefix + keyName).toLowerCase().replace(/\//g, '.');
            const className = namespace + leName;
            results.push({ key, className });
        }
    }
    return results;
}

function generatePHPCode(classes, className, namespace = '') {
    return `<?php
${namespace ? `\nnamespace ${namespace};\n` : ''}
/**
 * @phpstan-type Layers array{
${classes.map((c) => ` *  ${c.key}:\\${c.className},`).join('\n')}
 * }
 */
class ${className}
{
    public const LAYERS = [
${classes.map((c) => `        '${c.key}' => \\${c.className}::class,`).join('\n')}
    ];
}
`;
}

async function generatePHPFile(classes, outputFile, namespace = '') {
    const name = basename(outputFile, extname(outputFile));
    const className = name.charAt(0).toUpperCase() + name.slice(1);
    const code = generatePHPCode(classes, className, namespace);
    await writeFile(outputFile, code);
}

async function main() {
    try {
        const scanDir = argv[2] || defaultConfig.scanDir;
        const outputFile = argv[3] || defaultConfig.outputFile;
        const namespace = argv[4] || '';
        const classNamesapce = argv[5] || '';
        const classes = await findPHPClasses(scanDir, namespace);
        await generatePHPFile(classes, outputFile, classNamesapce);
        console.log(`Successfully generated ${outputFile}`);
    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
}

main();
