#!/usr/bin/env node

const { readdir, writeFile } = require('fs').promises;
const { join, relative, basename, extname } = require('path');
const { argv } = require('process');

const defaultConfig = {
    scanDir: './app/Layers',
    outputFile: './app/Layers.php',
};

const keyMap = {
    'a.d': 'ad',
};

async function findPHPClasses(dir, namespace = '', prefix = '', delsuffix = '') {
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
            let key = (prefix + keyName).toLowerCase().replace(/\//g, '.');
            const className = '\\' + namespace + leName;
            if (delsuffix != key) {
                if (delsuffix && key.endsWith(key)) {
                    key = key.slice(0, key.length - (delsuffix.length + 1));
                }
                results.push({ key: keyMap[key] ?? key, className });
            }
        }
    }
    return results;
}

function generatePHPType(name, map, type = 'array') {
    const body = Object.keys(map)
        .map((k) => ` *  ${k}: ${map[k]},`)
        .join('\n');
    return ` * @phpstan-type ${name} ${type}{\n${body}\n * }`;
}

function generatePHPConst(name, map) {
    const body = Object.keys(map)
        .map((k) => `        '${k}' => ${map[k]}::class,`)
        .join('\n');
    return `    public const ${name.toUpperCase()} = [\n${body}\n    ];`;
}

function generatePHPCode(name, map, className, namespace = '') {
    return `<?php\n${namespace ? `\nnamespace ${namespace};\n` : ''}
/**
${generatePHPType(name, map)}
 */
class ${className}
{
${generatePHPConst(name, map)}
}
`;
}

async function generateRegistry(
    scanDir,
    outputFile,
    { className = '', namespace = '', dirNamespace = '', typeName = '', delsuffix = '' } = {}
) {
    const classes = await findPHPClasses(scanDir, dirNamespace, '', delsuffix);
    const map = {};
    classes.map(({ key, className }) => (map[key] = className));
    const name = basename(outputFile, extname(outputFile));
    className ||= name.charAt(0).toUpperCase() + name.slice(1);
    const code = generatePHPCode(typeName, map, className, namespace);
    await writeFile(outputFile, code);
}

async function main() {
    try {
        const scanDir = argv[2] || defaultConfig.scanDir;
        const outputFile = argv[3] || defaultConfig.outputFile;
        const dirNamespace = argv[4] || '';
        const namespace = argv[5] || '';
        const typeName = argv[6] || '';
        const delsuffix = argv[7] || '';
        await generateRegistry(scanDir, outputFile, {
            dirNamespace,
            namespace,
            typeName,
            delsuffix,
        });
        console.log(`Successfully generated ${outputFile}`);
    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
}

main();
