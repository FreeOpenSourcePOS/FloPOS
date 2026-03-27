-- FloAdmin Database Schema
-- =========================
-- This database is for managing the FloPOS business (not merchant POS data)
-- 
-- Architecture:
-- - FloPOS (merchant app) runs locally with SQLite - they own ALL their data
-- - FloAdmin (our panel) runs on cloud - we manage subscriptions & support
-- - We ONLY store what we need for billing and support
--
-- What we DON'T store:
-- - Merchant's products, orders, customers, staff
-- - Any POS transaction data
--
-- What we DO store:
-- - Merchant accounts (for billing)
-- - Subscriptions
-- - Support call history
-- - Our team (internal staff)

-- ============================================
-- USERS TABLE
-- ============================================
-- Stores:
-- - Our internal team (super_admin, account_manager, reseller)
-- - Merchant owners (referenced by tenants.owner_id)
--
-- Roles:
-- - super_admin: Full access to FloAdmin
-- - account_manager: Manages merchants, handles support
-- - reseller: Brings merchants, earns commission
-- - merchant_owner: References to merchant account owners (for FK)
-- - staff: NOT used in FloAdmin (staff stay in merchant's SQLite)
CREATE TABLE public.users (
    id bigint NOT NULL DEFAULT nextval('users_id_seq'::regclass),
    email character varying(255) NOT NULL,
    phone character varying(20),
    country_code character varying(5) DEFAULT '+91'::character varying NOT NULL,
    password character varying(255),
    name character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    phone_verified_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    mobile_pairing_code character varying(6),
    mobile_pairing_code_rotated_at timestamp(0) without time zone,
    is_flopos_admin boolean DEFAULT false NOT NULL,
    role text DEFAULT 'staff'::text CHECK (role IN ('super_admin', 'account_manager', 'reseller', 'staff', 'merchant_owner')),
    company_name text,
    commission_percent numeric(5,2) DEFAULT 0
);

ALTER TABLE public.users OWNER TO flopos_user;

CREATE SEQUENCE public.users_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;
ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);

ALTER TABLE ONLY public.users ADD CONSTRAINT users_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.users ADD CONSTRAINT users_email_unique UNIQUE (email);
ALTER TABLE ONLY public.users ADD CONSTRAINT users_phone_unique UNIQUE (phone);
ALTER TABLE ONLY public.users ADD CONSTRAINT users_mobile_pairing_code_unique UNIQUE (mobile_pairing_code);

CREATE INDEX users_email_index ON public.users USING btree (email);
CREATE INDEX users_phone_index ON public.users USING btree (phone);
CREATE INDEX users_country_code_phone_index ON public.users USING btree (country_code, phone);

-- ============================================
-- SUPPORT CALLS TABLE
-- ============================================
-- Tracks support requests from merchants
CREATE TABLE public.support_calls (
    id text NOT NULL,
    merchant_id text NOT NULL,
    issue_type text NOT NULL,
    subject text NOT NULL,
    description text,
    duration_minutes integer,
    resolution text,
    handled_by bigint REFERENCES public.users(id) ON DELETE SET NULL,
    call_type text DEFAULT 'incoming'::text,
    status text DEFAULT 'open'::text,
    feedback_rating integer,
    called_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE public.support_calls OWNER TO flopos_user;
ALTER TABLE ONLY public.support_calls ADD CONSTRAINT support_calls_pkey PRIMARY KEY (id);

-- ============================================
-- TENANTS TABLE
-- ============================================
-- Represents a merchant's FloPOS installation
-- Linked to owner (user who purchased the subscription)
CREATE TABLE public.tenants (
    id bigint NOT NULL DEFAULT nextval('tenants_id_seq'::regclass),
    owner_id bigint NOT NULL REFERENCES public.users(id) ON DELETE RESTRICT,
    business_name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL UNIQUE,
    database_name character varying(255) NOT NULL UNIQUE,
    business_type character varying(255) DEFAULT 'restaurant'::character varying NOT NULL,
    country character varying(2) DEFAULT 'IN'::character varying NOT NULL,
    currency character varying(3) DEFAULT 'INR'::character varying NOT NULL,
    timezone character varying(255) DEFAULT 'Asia/Kolkata'::character varying NOT NULL,
    plan character varying(255) DEFAULT 'trial'::character varying NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    trial_ends_at timestamp(0) without time zone,
    suspended_at timestamp(0) without time zone,
    suspension_reason character varying(255),
    settings json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT tenants_business_type_check CHECK (business_type IN ('restaurant', 'salon', 'retail')),
    CONSTRAINT tenants_plan_check CHECK (plan IN ('trial', 'basic', 'premium', 'enterprise')),
    CONSTRAINT tenants_status_check CHECK (status IN ('active', 'suspended', 'cancelled'))
);

CREATE SEQUENCE public.tenants_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.tenants_id_seq OWNED BY public.tenants.id;
ALTER TABLE ONLY public.tenants ALTER COLUMN id SET DEFAULT nextval('public.tenants_id_seq'::regclass);
ALTER TABLE ONLY public.tenants ADD CONSTRAINT tenants_pkey PRIMARY KEY (id);

CREATE INDEX tenants_slug_index ON public.tenants USING btree (slug);
CREATE INDEX tenants_owner_id_index ON public.tenants USING btree (owner_id);
CREATE INDEX tenants_plan_index ON public.tenants USING btree (plan);
CREATE INDEX tenants_status_index ON public.tenants USING btree (status);

-- ============================================
-- SUBSCRIPTIONS TABLE
-- ============================================
-- Billing/subscription info per tenant
CREATE TABLE public.subscriptions (
    id bigint NOT NULL DEFAULT nextval('subscriptions_id_seq'::regclass),
    tenant_id bigint NOT NULL REFERENCES public.tenants(id) ON DELETE CASCADE,
    razorpay_subscription_id character varying(255),
    razorpay_customer_id character varying(255),
    plan character varying(255) DEFAULT 'trial'::character varying NOT NULL,
    amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    currency character varying(3) DEFAULT 'INR'::character varying NOT NULL,
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    current_period_start timestamp(0) without time zone,
    current_period_end timestamp(0) without time zone,
    cancelled_at timestamp(0) without time zone,
    paused_at timestamp(0) without time zone,
    metadata json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT subscriptions_plan_check CHECK (plan IN ('trial', 'basic', 'premium', 'enterprise')),
    CONSTRAINT subscriptions_status_check CHECK (status IN ('active', 'paused', 'cancelled', 'expired'))
);

CREATE SEQUENCE public.subscriptions_id_seq START WITH 1 INCREMENT BY 1 NO MINVALUE NO MAXVALUE CACHE 1;
ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;
ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);
ALTER TABLE ONLY public.subscriptions ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);
ALTER TABLE ONLY public.subscriptions ADD CONSTRAINT subscriptions_razorpay_subscription_id_unique UNIQUE (razorpay_subscription_id);

CREATE INDEX subscriptions_tenant_id_index ON public.subscriptions USING btree (tenant_id);
CREATE INDEX subscriptions_status_index ON public.subscriptions USING btree (status);
CREATE INDEX subscriptions_current_period_end_index ON public.subscriptions USING btree (current_period_end);

-- ============================================
-- SEED DATA: Default Admin Users
-- ============================================
-- Password: admin123
INSERT INTO public.users (name, email, password, role, is_flopos_admin, is_active) VALUES 
('Super Admin', 'admin@flopos.com', '$2a$10$bfR.tZvg/mIi9bVG0SBBRu8X8nhFKOviXdbLyb1gpqLkwEYUkDRw2', 'super_admin', true, true)
ON CONFLICT (email) DO UPDATE SET role = 'super_admin', is_flopos_admin = true;
