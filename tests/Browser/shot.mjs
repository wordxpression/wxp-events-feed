// Takes screenshots of a page with the events feed in headless Edge: node shot.mjs <url> <output folder>
// puppeteer-core is borrowed from the Restaurant Reservations plugin's dev dependencies.
import { createRequire } from 'node:module';
import path from 'node:path';
import fs from 'node:fs';

const require = createRequire( path.resolve( import.meta.dirname, '../../../wxp-restaurant-reservations/package.json' ) );
const puppeteer = require( 'puppeteer-core' );

const [ url, out ] = process.argv.slice( 2 );
fs.mkdirSync( out, { recursive: true } );
const browser = await puppeteer.launch( {
	executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
	headless: true,
	args: [ '--no-sandbox' ],
} );
const page = await browser.newPage();
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
for ( const [ name, width ] of [ [ 'desktop', 1100 ], [ 'mobile', 390 ] ] ) {
	await page.setViewport( { width, height: 900 } );
	await page.goto( url, { waitUntil: 'networkidle0' } );
	const info = await page.evaluate( () => ( {
		lists: document.querySelectorAll( '.wxp-ef__list' ).length,
		items: document.querySelectorAll( '.wxp-ef__item' ).length,
		newTab: [ ...document.querySelectorAll( '.wxp-ef__title a' ) ].every( ( a ) => a.target === '_blank' && /noopener/.test( a.rel ) ),
		hScroll: document.documentElement.scrollWidth > document.documentElement.clientWidth,
	} ) );
	console.log( name, JSON.stringify( info ) );
	const box = await page.$( '.entry-content, main, body' );
	await ( box ?? page ).screenshot( { path: path.join( out, `${ name }.png` ) } );
}
console.log( 'page errors:', errors.length ? errors.join( '; ' ) : 'none' );
await browser.close();
