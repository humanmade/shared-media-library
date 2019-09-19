import Attachment from './attachment';
const { Attachments } = wp.media.model;

const sharedAttachments = Attachments.extend( {
	/**
	 * @type {wp.media.model.Attachment}
	 */
	model: Attachment,
} );

/**
 * A collection of all attachments that have been fetched from the server.
 *
 * @static
 * @member {wp.media.model.Attachments}
 */
sharedAttachments.all = new sharedAttachments();

export default sharedAttachments;
