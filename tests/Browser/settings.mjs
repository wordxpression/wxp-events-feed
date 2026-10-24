// Drives the settings page of WXP Events Feed in headless Edge: node settings.mjs <session.json> <screenshot folder>
// The session file comes from run-settings.ps1 (a temporary administrator and its login cookies).
import { createRequire } from 'node:module';
import path from 'node:path';
import fs from 'node:fs';

const require = createRequire( path.resolve( import.meta.dirname, '../../../wxp-restaurant-reservations/package.json' ) );
const puppeteer = require( 'puppeteer-core' );

const session = JSON.parse( fs.readFileSync( process.argv[ 2 ], 'utf8' ) );
const shots = process.argv[ 3 ];
fs.mkdirSync( shots, { recursive: true } );
const host = new URL( session.home ).hostname;
const browser = await puppeteer.launch( {
	executablePath: 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
	headless: true,
	args: [ '--no-sandbox' ],
} );
const page = await browser.newPage();
await page.setViewport( { width: 1100, height: 900 } );
await page.setCookie(
	{ name: session.auth.name, value: session.auth.value, domain: host, path: '/' },
	{ name: session.logged_in.name, value: session.logged_in.value, domain: host, path: '/' }
);
const problems = [];
page.on( 'pageerror', ( e ) => problems.push( e.message ) );

let ok = 0;
let bad = 0;
const check = ( label, cond, extra = '' ) => {
	if ( cond ) {
		ok++;
	} else {
		bad++;
		console.log( 'FAIL', label, extra );
	}
};
const open = () =>
	page.goto( `${ session.home }/wp-admin/options-general.php?page=wxp-events-feed`, { waitUntil: 'networkidle2' } );
const field = async () => page.$eval( '#wxp-ef-source', ( el ) => el.value );
const notice = async () =>
	( await page.$eval( '.notice', ( el ) => el.textContent ).catch( () => '' ) ).trim();
const type = async ( value ) => {
	await page.$eval( '#wxp-ef-source', ( el ) => ( el.value = '' ) );
	if ( value ) {
		await page.type( '#wxp-ef-source', value );
	}
};
const press = async ( selector ) => {
	await Promise.all( [
		page.waitForNavigation( { waitUntil: 'networkidle2' } ),
		page.click( selector ),
	] );
};
const TEST = 'button[value="wxp_ef_test"]';

await open();
check( 'page opens', ( await page.title() ).includes( 'Events Feed' ) );
check( 'field starts empty', '' === ( await field() ) );
check(
	'test button is in the main form',
	await page.$eval( TEST, ( b ) => !! b.closest( 'form' ).querySelector( '#wxp-ef-source' ) )
);

// 1. A valid address that is not saved: the test works and the address stays.
await type( session.home );
await press( TEST );
let text = await notice();
check( 'success notice', /Connected/.test( text ), text );
check( 'says the address is not saved', /not saved yet/.test( text ), text );
check( 'address stays in the field', ( await field() ) === session.home, await field() );
await page.screenshot( { path: path.join( shots, 'test-unsaved.png' ) } );

// 2. Reloading shows the saved (still empty) value: nothing was saved by the test.
await open();
check( 'nothing saved by the test', '' === ( await field() ) );

// 3. A wrong address: the error shows and what was typed stays.
await type( 'http://127.0.0.1:9' );
await press( TEST );
text = await notice();
check( 'error notice', /answered with status|does not give|could not be reached/.test( text ), text );
check(
	'wrong address stays in the field',
	( await field() ) === 'http://127.0.0.1:9',
	await field()
);

// 4. An empty field.
await open();
await type( '' );
await press( TEST );
text = await notice();
check( 'empty field gives a clear message', /Fill in the address/.test( text ), text );

// 5. Save, then test with the saved address: no "not saved" hint.
await open();
await type( session.home );
await press( '#submit' );
check( 'saved', ( await field() ) === session.home, await field() );
await press( TEST );
text = await notice();
check( 'tested after saving', /Connected/.test( text ) && ! /not saved yet/.test( text ), text );
check( 'saved address stays', ( await field() ) === session.home );
await page.screenshot( { path: path.join( shots, 'test-saved.png' ) } );

// Other plugins on this site can log script errors on the admin screen; this page has no script of its own.
if ( problems.length ) {
	console.log( 'note: page errors from other plugins:', [ ...new Set( problems ) ].join( '; ' ) );
}
console.log( `settings page: ${ ok } ok, ${ bad } failed` );
await browser.close();
process.exit( bad ? 1 : 0 );
