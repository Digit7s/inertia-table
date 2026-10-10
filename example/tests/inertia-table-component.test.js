import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { compileScript, compileTemplate, parse } from '@vue/compiler-sfc';

const componentPath = fileURLToPath(
    new URL('../../resources/js/components/InertiaTable.vue', import.meta.url),
);

test('the package InertiaTable Vue single-file component parses and compiles', () => {
    const source = readFileSync(componentPath, 'utf8');
    const parsed = parse(source, { filename: componentPath });

    assert.deepEqual(parsed.errors, []);

    const script = compileScript(parsed.descriptor, { id: 'inertia-table' });
    const template = compileTemplate({
        source: parsed.descriptor.template.content,
        filename: componentPath,
        id: 'inertia-table',
        compilerOptions: { bindingMetadata: script.bindings },
    });

    assert.deepEqual(template.errors, []);
});
