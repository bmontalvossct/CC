import prettier from 'eslint-config-prettier';
import vue from 'eslint-plugin-vue';

import tseslint from 'typescript-eslint';
import vueParser from 'vue-eslint-parser';

export default [
    {
        ignores: ['vendor/**', 'node_modules/**', 'public/**', 'bootstrap/ssr/**', 'tailwind.config.js', 'resources/js/components/ui/*', 'dist/**', 'packaging/**', 'scratch/**'],
    },
    ...vue.configs['flat/essential'],
    ...tseslint.configs.recommended.map((config) => ({ ...config, files: ['**/*.{ts,tsx,vue}'] })),
    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: { parser: tseslint.parser, extraFileExtensions: ['.vue'] },
        },
    },
    {
        plugins: { '@typescript-eslint': tseslint.plugin },
        rules: {
            'vue/multi-word-component-names': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/no-unused-vars': [
                'warn',
                {
                    argsIgnorePattern: '^_',
                    varsIgnorePattern: '^_',
                    caughtErrorsIgnorePattern: '^_',
                },
            ],
        },
    },
    prettier,
];
