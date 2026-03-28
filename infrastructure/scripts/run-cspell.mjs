#!/usr/bin/env node

import { existsSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { join } from 'node:path';

const repoRoot = process.cwd();
const localCspell = join( repoRoot, 'node_modules', '.bin', process.platform === 'win32' ? 'cspell.cmd' : 'cspell' );

const cspellArgs = [
	'--config',
	'.vscode/cspell.json',
	'--no-progress',
	'--gitignore',
	'README.md',
	'CONTRIBUTING.md',
	'docs/**/*.md',
	'src/**/*.php',
	'includes/**/*.php',
	'tests/**/*.php',
	'.github/**/*.yml',
	'composer.json',
	'package.json',
];

const command = existsSync( localCspell ) ? localCspell : 'npx';
const args = existsSync( localCspell ) ? cspellArgs : [ '--yes', 'cspell', ...cspellArgs ];

const result = spawnSync( command, args, { stdio: 'inherit' } );
if ( typeof result.status === 'number' ) {
	process.exit( result.status );
}

process.exit( 1 );
