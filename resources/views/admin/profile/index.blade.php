@extends('admin.layout')

@section('content')
    <section id="admin-profile-page" class="space-y-6">
        <div class="admin-shell-card p-4 sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-900">Edit Profile</h2>
                    <p class="mt-1 text-sm text-slate-500">Update your profile information.</p>
                </div>
            </div>

            <form id="admin-profile-form" class="admin-profile-form mt-6 space-y-6" novalidate>
                <div class="admin-profile-layout">
                    <div class="admin-profile-photo-panel">
                        <p class="admin-auth-label">Profile Photo</p>
                        <label for="admin-profile-photo" class="admin-profile-avatar-wrap admin-profile-avatar-trigger">
                            <img
                                id="admin-profile-preview"
                                src=""
                                alt="Profile preview"
                                class="admin-profile-avatar-img hidden"
                                data-profile-preview
                            >
                            <span id="admin-profile-initials" class="admin-profile-avatar-fallback" data-profile-initials>--</span>
                            <span class="admin-profile-avatar-overlay" aria-hidden="true">
                                <i class="fas fa-camera"></i>
                            </span>
                        </label>
                        <div class="admin-profile-photo-actions">
                            <label class="admin-profile-upload-button" for="admin-profile-photo">
                                <i class="fas fa-camera mr-2"></i>
                                Choose Photo
                            </label>
                            <button
                                type="button"
                                class="admin-profile-remove-button hidden"
                                data-profile-remove-photo
                            >
                                Remove Photo
                            </button>
                        </div>
                        <input
                            id="admin-profile-photo"
                            name="profile_photo"
                            type="file"
                            accept="image/jpeg,image/png,image/jpg,image/gif,image/webp"
                            class="sr-only"
                            data-profile-photo-input
                        >
                        <p class="admin-profile-photo-hint">JPEG, PNG, GIF, or WebP. Max 5 MB.</p>
                        <p id="profile_photo-error" class="admin-auth-error" data-field-error="profile_photo"></p>
                    </div>

                    <div class="admin-profile-fields">
                        <div class="admin-profile-section">
                            <h3 class="admin-profile-section-title">Personal Information</h3>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="admin-auth-label" for="admin-profile-first-name">First Name</label>
                                    <input
                                        id="admin-profile-first-name"
                                        name="first_name"
                                        type="text"
                                        autocomplete="given-name"
                                        class="admin-auth-input"
                                        required
                                    >
                                    <p id="first_name-error" class="admin-auth-error" data-field-error="first_name"></p>
                                </div>
                                <div>
                                    <label class="admin-auth-label" for="admin-profile-last-name">Last Name</label>
                                    <input
                                        id="admin-profile-last-name"
                                        name="last_name"
                                        type="text"
                                        autocomplete="family-name"
                                        class="admin-auth-input"
                                        required
                                    >
                                    <p id="last_name-error" class="admin-auth-error" data-field-error="last_name"></p>
                                </div>
                            </div>
                        </div>

                        <div class="admin-profile-section-divider"></div>

                        <div class="admin-profile-section">
                            <h3 class="admin-profile-section-title">Account Information</h3>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="admin-auth-label" for="admin-profile-email">Email</label>
                                    <input
                                        id="admin-profile-email"
                                        type="email"
                                        class="admin-auth-input admin-profile-readonly"
                                        disabled
                                        data-profile-email
                                    >
                                    <p class="mt-1.5 text-xs text-slate-500">Email cannot be changed here.</p>
                                </div>

                                <div>
                                    <label class="admin-auth-label" for="admin-profile-role">Role</label>
                                    <input
                                        id="admin-profile-role"
                                        type="text"
                                        class="admin-auth-input admin-profile-readonly"
                                        disabled
                                        data-profile-role
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-profile-section-divider"></div>

                <div class="admin-profile-section" id="admin-profile-password-section">
                    <h3 class="admin-profile-section-title">Change Password</h3>
                    <p class="admin-profile-section-description">Leave blank if you do not want to change your password.</p>
                    <div class="mt-4 grid gap-4 lg:grid-cols-3">
                        <div>
                            <label class="admin-auth-label" for="admin-profile-current-password">Current Password</label>
                            <div class="relative">
                                <input
                                    id="admin-profile-current-password"
                                    name="current_password"
                                    type="password"
                                    autocomplete="current-password"
                                    class="admin-auth-input pr-12"
                                    data-profile-password-field
                                >
                                <button
                                    type="button"
                                    class="admin-password-toggle"
                                    data-password-toggle="admin-profile-current-password"
                                    aria-label="Toggle current password visibility"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="current_password-error" class="admin-auth-error" data-field-error="current_password"></p>
                        </div>
                        <div>
                            <label class="admin-auth-label" for="admin-profile-new-password">New Password</label>
                            <div class="relative">
                                <input
                                    id="admin-profile-new-password"
                                    name="password"
                                    type="password"
                                    autocomplete="new-password"
                                    class="admin-auth-input pr-12"
                                    data-profile-password-field
                                >
                                <button
                                    type="button"
                                    class="admin-password-toggle"
                                    data-password-toggle="admin-profile-new-password"
                                    aria-label="Toggle new password visibility"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="password-error" class="admin-auth-error" data-field-error="password"></p>
                        </div>
                        <div>
                            <label class="admin-auth-label" for="admin-profile-password-confirmation">Confirm New Password</label>
                            <div class="relative">
                                <input
                                    id="admin-profile-password-confirmation"
                                    name="password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    class="admin-auth-input pr-12"
                                    data-profile-password-field
                                >
                                <button
                                    type="button"
                                    class="admin-password-toggle"
                                    data-password-toggle="admin-profile-password-confirmation"
                                    aria-label="Toggle password confirmation visibility"
                                >
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <p id="password_confirmation-error" class="admin-auth-error" data-field-error="password_confirmation"></p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <button type="button" class="admin-secondary-button" data-profile-password-submit>
                            <span data-profile-password-submit-label>Update Password</span>
                        </button>
                    </div>
                </div>

                <div class="admin-profile-form-actions">
                    <a href="/admin/dashboard" class="admin-secondary-button text-center">Cancel</a>
                    <button type="submit" class="admin-primary-button" data-profile-submit>
                        <span data-profile-submit-label>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </section>
@endsection
