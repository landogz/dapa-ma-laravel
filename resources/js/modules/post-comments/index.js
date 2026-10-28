import axios from 'axios';
import { confirmWithSwal, formatApiValidationMessage } from '../shared/swal-forms';
import { showErrorToast, showSuccessToast } from '../shared/toast';

/**
 * Confirm and delete a post comment from the admin post view.
 *
 * @param {number|string} postId
 * @param {number|string} commentId
 * @returns {Promise<{ deleted: boolean }>}
 */
export async function confirmDeletePostComment(postId, commentId) {
    const confirmed = await confirmWithSwal({
        icon: 'warning',
        title: 'Delete this comment?',
        text: 'This also removes any replies. This cannot be undone.',
        confirmButtonText: 'Delete comment',
        danger: true,
    });

    if (!confirmed.isConfirmed) {
        return { deleted: false };
    }

    try {
        const { data } = await axios.delete(`/admin/posts/${postId}/comments/${commentId}`);
        showSuccessToast(data?.message ?? 'Comment deleted successfully.', 'Deleted');
        return { deleted: true };
    } catch (error) {
        showErrorToast(
            formatApiValidationMessage(
                error?.response,
                error?.response?.data?.message ?? 'Unable to delete this comment.',
            ),
            'Error',
        );
        return { deleted: false };
    }
}

export function bindPostCommentDeletes(root, onDelete) {
    if (!root || typeof onDelete !== 'function') {
        return;
    }

    root.querySelectorAll('[data-post-comment-delete]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const commentId = button.getAttribute('data-post-comment-delete');
            const postId = button.getAttribute('data-post-id');

            if (!commentId || !postId) {
                return;
            }

            onDelete(postId, commentId);
        });
    });
}
