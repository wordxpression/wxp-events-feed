import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Events', 'wxp-events-feed' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Number of events', 'wxp-events-feed' ) }
						min={ 1 }
						max={ 20 }
						value={ attributes.limit }
						onChange={ ( limit ) =>
							setAttributes( { limit: limit || 5 } )
						}
					/>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Categories', 'wxp-events-feed' ) }
						help={ __(
							'Category slugs of the events website, comma separated. Empty shows all events.',
							'wxp-events-feed'
						) }
						value={ attributes.category }
						onChange={ ( category ) =>
							setAttributes( { category } )
						}
					/>
				</PanelBody>
				<PanelBody title={ __( 'Show', 'wxp-events-feed' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Image', 'wxp-events-feed' ) }
						checked={ attributes.image }
						onChange={ ( image ) => setAttributes( { image } ) }
					/>
					{ attributes.image && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Image size', 'wxp-events-feed' ) }
							value={ attributes.imageSize }
							options={ [
								{
									value: 'thumbnail',
									label: __( 'Small', 'wxp-events-feed' ),
								},
								{
									value: 'medium',
									label: __( 'Medium', 'wxp-events-feed' ),
								},
							] }
							onChange={ ( imageSize ) =>
								setAttributes( { imageSize } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Short description', 'wxp-events-feed' ) }
						checked={ attributes.description }
						onChange={ ( description ) =>
							setAttributes( { description } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Time', 'wxp-events-feed' ) }
						checked={ attributes.time }
						onChange={ ( time ) => setAttributes( { time } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Date format', 'wxp-events-feed' ) }
						value={ attributes.dateFrom }
						options={ [
							{
								value: 'site',
								label: __(
									'Like this website',
									'wxp-events-feed'
								),
							},
							{
								value: 'source',
								label: __(
									'Like the events website',
									'wxp-events-feed'
								),
							},
						] }
						onChange={ ( dateFrom ) =>
							setAttributes( { dateFrom } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Title element', 'wxp-events-feed' ) }
						value={ attributes.heading }
						options={ [ 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ].map(
							( value ) => ( { value, label: value } )
						) }
						onChange={ ( heading ) => setAttributes( { heading } ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<Disabled>
					<ServerSideRender
						block="wxp/events-feed"
						attributes={ attributes }
					/>
				</Disabled>
			</div>
		</>
	);
}
