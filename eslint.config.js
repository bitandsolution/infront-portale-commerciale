import pluginVue from 'eslint-plugin-vue';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';

export default tseslint.config(
    {
        ignores: ['vendor/**', 'node_modules/**', 'public/**', 'bootstrap/ssr/**', 'storage/**'],
    },
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
                sourceType: 'module',
            },
        },
    },
    {
        languageOptions: {
            globals: { ...globals.browser },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/html-indent': ['error', 4],
        },
    },
);
