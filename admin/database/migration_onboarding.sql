-- ============================================================
--  Migration: employee onboarding fields + documents
--  Run this ONCE on an existing install that was created before
--  onboarding existed. Fresh installs already get these columns
--  from schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

ALTER TABLE users
  ADD COLUMN phone                    VARCHAR(20)  NULL AFTER email,
  ADD COLUMN alternate_phone          VARCHAR(20)  NULL AFTER phone,
  ADD COLUMN address                  TEXT         NULL AFTER alternate_phone,
  ADD COLUMN aadhar_number            VARCHAR(20)  NULL AFTER address,
  ADD COLUMN pan_number               VARCHAR(20)  NULL AFTER aadhar_number,
  ADD COLUMN designation              VARCHAR(100) NULL AFTER pan_number,
  ADD COLUMN date_of_joining          DATE         NULL AFTER designation,
  ADD COLUMN date_of_birth            DATE         NULL AFTER date_of_joining,
  ADD COLUMN emergency_contact_name   VARCHAR(100) NULL AFTER date_of_birth,
  ADD COLUMN emergency_contact_phone  VARCHAR(20)  NULL AFTER emergency_contact_name,
  ADD COLUMN profile_photo            VARCHAR(255) NULL AFTER emergency_contact_phone,
  ADD COLUMN aadhar_front             VARCHAR(255) NULL AFTER profile_photo,
  ADD COLUMN aadhar_back              VARCHAR(255) NULL AFTER aadhar_front,
  ADD COLUMN pan_card_image           VARCHAR(255) NULL AFTER aadhar_back;
