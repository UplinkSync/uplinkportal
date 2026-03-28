/**
 * UplinkPortal JS build script.
 *
 * Minifies source JS files in assets/js/ and outputs to assets/js/dist/.
 * Generates an asset-manifest.json with content hashes for PHP cache-busting.
 *
 * Usage: npm run build
 */

import { build } from 'esbuild';
import { createHash } from 'crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname( fileURLToPath( import.meta.url ) );

const ENTRIES = [
	'admin-backend',
	'admin-configuration',
	'admin-dashboard',
	'admin-modules',
	'admin-onboarding',
	'admin-validation',
	'portal',
];

const SRC_DIR  = join( __dirname, 'assets', 'js' );
const DIST_DIR = join( __dirname, 'assets', 'js', 'dist' );

mkdirSync( DIST_DIR, { recursive: true } );

const manifest = {};

for ( const name of ENTRIES ) {
	const inFile  = join( SRC_DIR, `${name}.js` );
	const outFile = join( DIST_DIR, `${name}.min.js` );

	await build( {
		entryPoints: [ inFile ],
		outfile:     outFile,
		minify:      true,
		bundle:      false,
		logLevel:    'silent',
	} );

	const content = readFileSync( outFile );
	const hash    = createHash( 'md5' ).update( content ).digest( 'hex' ).slice( 0, 8 );

	manifest[ `${name}.js` ] = { file: `dist/${name}.min.js`, hash };
}

const manifestPath = join( DIST_DIR, 'asset-manifest.json' );
writeFileSync( manifestPath, JSON.stringify( manifest, null, '\t' ) + '\n' );

console.log( 'Build complete. Manifest written to assets/js/dist/asset-manifest.json' );
