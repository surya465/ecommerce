<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'pp_textiles' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'H9+g,F85Kamd}WLG|>|-4=H$GF1cE@nTFGUlJWzsoLQ?AGo1he0$>V%_&,:LcWnA' );
define( 'SECURE_AUTH_KEY',  'mNqC&ctVhema!gL`/{ UAmfV%qhN~LqE0osF-]E*<gxLWu3]% Y5qaX$&su-cXwJ' );
define( 'LOGGED_IN_KEY',    ':#)ch,QLvnX,nRl%]E*gi6qNf@p%tW;K~qmkDUUlU|)[ou-L7<b;|a(_Qt(P@.1U' );
define( 'NONCE_KEY',        '8Xb=RM$Z6%CBO5k Yzm(dGs,tC)V|b+Ss)]pMAIeP(YIcE.[q>ujkuJr,a5(w:0]' );
define( 'AUTH_SALT',        ']I1m3dzv@FLdcuIo=2^)UDq0B`mb_3fT6Uy$%O?v9;MY-_?|5;2_Vx`5lh<|O{38' );
define( 'SECURE_AUTH_SALT', 'W$(I-SstR_zCr9AU4f  A7Lq_&T_PXJ!FfKhKx K2w6-HfXwVI+lUta[rN:MDpp<' );
define( 'LOGGED_IN_SALT',   '^V}p0xP-Ul$.KDvGKLZ-]432Z`Pw$&tyK}E4awD#nw{;Cvde((:oR(8iv;e5B8gA' );
define( 'NONCE_SALT',       'Mh2Sy|b7=!Z#V/6C:;/M_yKoqK}iQQW$g+4;S[[]aSI4P>HGH/JZS6dq(Nrs84:5' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
