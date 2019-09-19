/**
 * Overrides for the wp.media library.
 */

import Attachment from './models/attachment';
import Attachments from './models/attachments';

// Overwrite models.
wp.media.model.Attachment  = Attachment;
wp.media.model.Attachments = Attachments;
