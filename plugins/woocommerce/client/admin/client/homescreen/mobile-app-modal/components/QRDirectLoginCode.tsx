/**
 * External dependencies
 */
import { QRCodeSVG } from 'qrcode.react';
import React, { useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { sprintf, __ } from '@wordpress/i18n';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import { useQRLoginToken, QRLoginTokenStates } from './useQRLoginToken';

export const QRDirectLoginCode = () => {
	const {
		state,
		qrUrl,
		secondsRemaining,
		errorMessage,
		fetchToken,
		refreshToken,
	} = useQRLoginToken();

	useEffect( () => {
		fetchToken();
		recordEvent( 'mobile_app_qr_direct_login_displayed' );
	}, [ fetchToken ] );

	const formatTime = ( seconds: number ) => {
		const mins = Math.floor( seconds / 60 );
		const secs = seconds % 60;
		return `${ mins }:${ secs.toString().padStart( 2, '0' ) }`;
	};

	if ( state === QRLoginTokenStates.LOADING ) {
		return (
			<div className="qr-direct-login">
				<Spinner />
				<p>{ __( 'Generating secure login code…', 'woocommerce' ) }</p>
			</div>
		);
	}

	if ( state === QRLoginTokenStates.ERROR ) {
		return (
			<div className="qr-direct-login">
				<p className="qr-direct-login__error">{ errorMessage }</p>
				<Button variant="secondary" onClick={ refreshToken }>
					{ __( 'Try again', 'woocommerce' ) }
				</Button>
			</div>
		);
	}

	if ( state === QRLoginTokenStates.EXPIRED ) {
		return (
			<div className="qr-direct-login">
				<p>{ __( 'The login code has expired.', 'woocommerce' ) }</p>
				<Button
					variant="secondary"
					onClick={ () => {
						recordEvent( 'mobile_app_qr_direct_login_refreshed' );
						refreshToken();
					} }
				>
					{ __( 'Generate new code', 'woocommerce' ) }
				</Button>
			</div>
		);
	}

	if ( state === QRLoginTokenStates.READY && qrUrl ) {
		return (
			<div className="qr-direct-login">
				<QRCodeSVG value={ qrUrl } size={ 140 } />
				<p className="qr-direct-login__timer">
					{ sprintf(
						/* translators: %s: time remaining in M:SS format */
						__( 'Code expires in %s', 'woocommerce' ),
						formatTime( secondsRemaining )
					) }
				</p>
			</div>
		);
	}

	return null;
};
