<?php

return [
    'success' => 'Operation completed successfully.',
    'unauthorized' => 'You need to sign in to continue.',
    'forbidden' => 'You don\'t have permission to access this resource.',
    'not_found' => 'Record not found.',
    'server_error' => 'Unexpected error. Please try again.',
    'invalid_payload' => 'Invalid data.',
    'too_many_requests' => 'Too many attempts. Wait a moment and try again.',
    'maintenance' => 'The system is being updated. Try again in a moment.',
    'concurrency_conflict' => 'Another operation changed this data at the same time. Nothing was saved: try again.',

    'auth' => [
        'invalid_credentials' => 'Invalid email or password.',
        'logged_in' => 'Signed in successfully.',
        'logged_out' => 'Signed out.',
        'pending_approval' => 'Your registration is still awaiting an administrator\'s approval.',
        'password_changed' => 'Password changed. Your other sessions were signed out.',
        'current_password_invalid' => 'The current password is incorrect.',
        'password_required_fields' => 'Current and new password are required.',
        'password_change_error' => 'Error changing the password.',
    ],

    'registration_requests' => [
        'not_found' => 'Registration request not found.',
        'required_fields' => 'Name, email and password are required.',
        'name_too_long' => 'Name must be at most 150 characters.',
        'invalid_email' => 'Invalid email.',
        'password_too_short' => 'Password must be at least 8 characters.',
        'password_too_long' => 'Password must be at most 255 characters.',
        'email_taken' => 'A user or registration request with this email already exists.',
        'created' => 'Registration request sent. Wait for an administrator\'s approval.',
        'approved' => 'Registration approved. The user can now sign in.',
        'rejected' => 'Registration request rejected and deleted.',
        'fetch_error' => 'Error fetching registration requests.',
        'create_error' => 'Error sending the registration request.',
        'approve_error' => 'Error approving the registration request.',
        'reject_error' => 'Error rejecting the registration request.',
    ],

    'users' => [
        'not_found' => 'User not found.',
        'cannot_delete_admin' => 'Administrators cannot be deleted.',
        'deleted' => 'User deleted. Their access has been revoked.',
        'fetch_error' => 'Error fetching users.',
        'delete_error' => 'Error deleting user.',
        'cannot_reset_admin' => 'An administrator\'s password cannot be reset by another administrator.',
        'password_reset' => 'Password reset. The user\'s sessions were signed out.',
        'password_reset_error' => 'Error resetting the password.',
    ],

    'places' => [
        'not_found' => 'Place not found.',
        'invalid_coordinates' => 'Invalid coordinates: latitude goes from -90 to 90 and longitude from -180 to 180.',
        'invalid_radius' => 'The radius must be greater than zero and at most :max km.',
        'invalid_limit' => 'The limit must be between 1 and :max.',
        'invalid_page_size' => 'The page size must be between 1 and :max.',
        'invalid_cursor' => 'Invalid pagination cursor.',
        'invalid_search' => 'The search term and category must be at most 100 characters.',
        'theme_required' => 'Provide the itinerary theme.',
    ],

    'locations' => [
        'invalid_parent' => 'Invalid parent location.',
        'invalid_kind' => 'The location kind must be country, state, region or city.',
    ],

    'tours' => [
        'invalid_places' => 'Choose from 1 to :max places.',
    ],

    'related' => [
        'author' => 'Same author: :value',
        'style' => 'Same style: :value',
        'theme' => 'Same theme: :value',
    ],

    'visits' => [
        'invalid_rating' => 'The rating must be a whole number from 1 to 5.',
    ],

    'follows' => [
        'cannot_follow_self' => 'You cannot follow yourself.',
    ],

    'itineraries' => [
        'title_required' => 'The itinerary title is required (up to 150 characters).',
        'stops_required' => 'The itinerary needs at least one stop (at most :max).',
        'invalid_stop' => 'Each stop needs a place, a day and an order greater than zero.',
        'duplicate_stop' => 'Two stops cannot have the same day and order.',
        'invalid_place' => 'One of the itinerary places does not exist.',
    ],
];
