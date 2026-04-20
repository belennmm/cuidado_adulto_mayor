--
-- PostgreSQL database dump
--

\restrict tKXmLbtyh1WQ2vcPFtztmdIsuHWTUP5GHETTe36TJSiTOFufIZMNiDRYbthw8Qi

-- Dumped from database version 16.13
-- Dumped by pg_dump version 16.13

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: actividad; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.actividad (
    id_actividad integer NOT NULL,
    nombre_actividad character varying(250) NOT NULL,
    descripcion text,
    hora_programada time without time zone NOT NULL,
    tipo_actividad character varying(100) NOT NULL,
    id_adultomayor integer NOT NULL
);


ALTER TABLE public.actividad OWNER TO cuidado_user;

--
-- Name: actividad_id_actividad_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.actividad_id_actividad_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.actividad_id_actividad_seq OWNER TO cuidado_user;

--
-- Name: actividad_id_actividad_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.actividad_id_actividad_seq OWNED BY public.actividad.id_actividad;


--
-- Name: adulto_mayor; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.adulto_mayor (
    id_adultomayor integer NOT NULL,
    nombre character varying(250) NOT NULL,
    fecha_nacimiento date NOT NULL,
    estado_general text,
    observaciones text,
    idadministrador integer NOT NULL
);


ALTER TABLE public.adulto_mayor OWNER TO cuidado_user;

--
-- Name: adulto_mayor_id_adultomayor_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.adulto_mayor_id_adultomayor_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.adulto_mayor_id_adultomayor_seq OWNER TO cuidado_user;

--
-- Name: adulto_mayor_id_adultomayor_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.adulto_mayor_id_adultomayor_seq OWNED BY public.adulto_mayor.id_adultomayor;


--
-- Name: asignacion_cuidador; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.asignacion_cuidador (
    iduser integer NOT NULL,
    id_adultomayor integer NOT NULL,
    tipo_cuidador character varying(20) NOT NULL,
    CONSTRAINT chk_tipo_cuidador CHECK (((tipo_cuidador)::text = ANY ((ARRAY['familiar'::character varying, 'profesional'::character varying])::text[])))
);


ALTER TABLE public.asignacion_cuidador OWNER TO cuidado_user;

--
-- Name: cache; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache OWNER TO cuidado_user;

--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


ALTER TABLE public.cache_locks OWNER TO cuidado_user;

--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


ALTER TABLE public.failed_jobs OWNER TO cuidado_user;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.failed_jobs_id_seq OWNER TO cuidado_user;

--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


ALTER TABLE public.job_batches OWNER TO cuidado_user;

--
-- Name: jobs; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


ALTER TABLE public.jobs OWNER TO cuidado_user;

--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.jobs_id_seq OWNER TO cuidado_user;

--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: medicamento; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.medicamento (
    id_medicamento integer NOT NULL,
    nombre_medicamento character varying(250) NOT NULL,
    dosis character varying(100) NOT NULL,
    frecuencia character varying(100) NOT NULL,
    indicaciones text,
    id_adultomayor integer NOT NULL
);


ALTER TABLE public.medicamento OWNER TO cuidado_user;

--
-- Name: medicamento_id_medicamento_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.medicamento_id_medicamento_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.medicamento_id_medicamento_seq OWNER TO cuidado_user;

--
-- Name: medicamento_id_medicamento_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.medicamento_id_medicamento_seq OWNED BY public.medicamento.id_medicamento;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


ALTER TABLE public.migrations OWNER TO cuidado_user;

--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.migrations_id_seq OWNER TO cuidado_user;

--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


ALTER TABLE public.password_reset_tokens OWNER TO cuidado_user;

--
-- Name: personal_access_tokens; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.personal_access_tokens OWNER TO cuidado_user;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.personal_access_tokens_id_seq OWNER TO cuidado_user;

--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;


--
-- Name: recordatorio; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.recordatorio (
    id_recordatorio integer NOT NULL,
    hora_recordatorio time without time zone NOT NULL,
    mensaje text NOT NULL,
    id_actividad integer NOT NULL
);


ALTER TABLE public.recordatorio OWNER TO cuidado_user;

--
-- Name: recordatorio_id_recordatorio_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.recordatorio_id_recordatorio_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.recordatorio_id_recordatorio_seq OWNER TO cuidado_user;

--
-- Name: recordatorio_id_recordatorio_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.recordatorio_id_recordatorio_seq OWNED BY public.recordatorio.id_recordatorio;


--
-- Name: registro_cumplimiento; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.registro_cumplimiento (
    id_actividad integer NOT NULL,
    iduser integer NOT NULL,
    fecha_cumplimiento timestamp without time zone NOT NULL,
    estado_cumplimiento character varying(50) NOT NULL,
    observacion text,
    CONSTRAINT chk_estado_cumplimiento CHECK (((estado_cumplimiento)::text = ANY ((ARRAY['pendiente'::character varying, 'completada'::character varying, 'omitida'::character varying])::text[])))
);


ALTER TABLE public.registro_cumplimiento OWNER TO cuidado_user;

--
-- Name: sessions; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


ALTER TABLE public.sessions OWNER TO cuidado_user;

--
-- Name: users; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    role character varying(255) DEFAULT 'familiar'::character varying NOT NULL,
    is_approved boolean DEFAULT false NOT NULL,
    location character varying(255),
    phone character varying(255),
    birthdate date,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.users OWNER TO cuidado_user;

--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO cuidado_user;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: usuarios; Type: TABLE; Schema: public; Owner: cuidado_user
--

CREATE TABLE public.usuarios (
    id bigint NOT NULL,
    nombre character varying(255) NOT NULL,
    correo character varying(255) NOT NULL,
    password character varying(255) NOT NULL,
    rol character varying(255) NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


ALTER TABLE public.usuarios OWNER TO cuidado_user;

--
-- Name: usuarios_id_seq; Type: SEQUENCE; Schema: public; Owner: cuidado_user
--

CREATE SEQUENCE public.usuarios_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.usuarios_id_seq OWNER TO cuidado_user;

--
-- Name: usuarios_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: cuidado_user
--

ALTER SEQUENCE public.usuarios_id_seq OWNED BY public.usuarios.id;


--
-- Name: actividad id_actividad; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.actividad ALTER COLUMN id_actividad SET DEFAULT nextval('public.actividad_id_actividad_seq'::regclass);


--
-- Name: adulto_mayor id_adultomayor; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.adulto_mayor ALTER COLUMN id_adultomayor SET DEFAULT nextval('public.adulto_mayor_id_adultomayor_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: medicamento id_medicamento; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.medicamento ALTER COLUMN id_medicamento SET DEFAULT nextval('public.medicamento_id_medicamento_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: personal_access_tokens id; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);


--
-- Name: recordatorio id_recordatorio; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.recordatorio ALTER COLUMN id_recordatorio SET DEFAULT nextval('public.recordatorio_id_recordatorio_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: usuarios id; Type: DEFAULT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.usuarios ALTER COLUMN id SET DEFAULT nextval('public.usuarios_id_seq'::regclass);


--
-- Data for Name: actividad; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.actividad (id_actividad, nombre_actividad, descripcion, hora_programada, tipo_actividad, id_adultomayor) FROM stdin;
\.


--
-- Data for Name: adulto_mayor; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.adulto_mayor (id_adultomayor, nombre, fecha_nacimiento, estado_general, observaciones, idadministrador) FROM stdin;
\.


--
-- Data for Name: asignacion_cuidador; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.asignacion_cuidador (iduser, id_adultomayor, tipo_cuidador) FROM stdin;
\.


--
-- Data for Name: cache; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.cache (key, value, expiration) FROM stdin;
\.


--
-- Data for Name: cache_locks; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.cache_locks (key, owner, expiration) FROM stdin;
\.


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.failed_jobs (id, uuid, connection, queue, payload, exception, failed_at) FROM stdin;
\.


--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.job_batches (id, name, total_jobs, pending_jobs, failed_jobs, failed_job_ids, options, cancelled_at, created_at, finished_at) FROM stdin;
\.


--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
\.


--
-- Data for Name: medicamento; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.medicamento (id_medicamento, nombre_medicamento, dosis, frecuencia, indicaciones, id_adultomayor) FROM stdin;
\.


--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_04_17_224736_create_usuarios_table	1
5	2026_04_18_000000_create_personal_access_tokens_table	1
6	2026_04_19_000001_add_is_approved_to_users_table	1
7	2026_04_19_000002_mark_caregivers_as_pending	1
\.


--
-- Data for Name: password_reset_tokens; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.password_reset_tokens (email, token, created_at) FROM stdin;
\.


--
-- Data for Name: personal_access_tokens; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
1	App\\Models\\User	1	API Token	b9006ec8c568adcd6c7d006058d85cb803d6e7391b432be66b5cbcbb0d9f94a2	["*"]	\N	\N	2026-04-20 05:10:54	2026-04-20 05:10:54
2	App\\Models\\User	1	API Token	c901a31a76ed5db1e967762edb72a055bf63c55e4784e1f0488b54faa68179be	["*"]	\N	\N	2026-04-20 05:14:19	2026-04-20 05:14:19
3	App\\Models\\User	3	API Token	51c6071cb62057c93ac41665150de219be74ab08340cbe5f37d8502c61eef20b	["*"]	\N	\N	2026-04-20 05:20:50	2026-04-20 05:20:50
4	App\\Models\\User	4	API Token	520b3b4734fc897e19dda04ec53b81b880ddb71d7100abd044cdb1a412a86cb0	["*"]	\N	\N	2026-04-20 05:24:06	2026-04-20 05:24:06
5	App\\Models\\User	5	API Token	d00d04708bba8c5f183a1e9f8475d6544371052e2f582e38b3794f12701bf1a3	["*"]	\N	\N	2026-04-20 05:26:22	2026-04-20 05:26:22
6	App\\Models\\User	3	API Token	660723a4375be955d21cfda426a434576420bb9ef01d8e768789738b818d7f46	["*"]	2026-04-20 05:27:35	\N	2026-04-20 05:27:31	2026-04-20 05:27:35
7	App\\Models\\User	6	API Token	66efab2049899c07dc8a7c89f3f585d11793928e1014649cda2d361d0e76627a	["*"]	\N	\N	2026-04-20 05:27:52	2026-04-20 05:27:52
8	App\\Models\\User	1	API Token	d5033e1c95ba4cc546fe6375b1ab1b6e87e4a29751fe444fcd715dd445a79733	["*"]	2026-04-20 05:30:34	\N	2026-04-20 05:30:31	2026-04-20 05:30:34
9	App\\Models\\User	7	API Token	1a45de661d3e8e47b9e2c1f7e5ac61ccc4a601f810eee9163e18039dc2c3e42f	["*"]	\N	\N	2026-04-20 05:30:39	2026-04-20 05:30:39
10	App\\Models\\User	1	API Token	02086140723d6c9f8b176c9ab465cd5658287422fc7e2cd7594735a7cb66ca86	["*"]	\N	\N	2026-04-20 07:47:50	2026-04-20 07:47:50
\.


--
-- Data for Name: recordatorio; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.recordatorio (id_recordatorio, hora_recordatorio, mensaje, id_actividad) FROM stdin;
\.


--
-- Data for Name: registro_cumplimiento; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.registro_cumplimiento (id_actividad, iduser, fecha_cumplimiento, estado_cumplimiento, observacion) FROM stdin;
\.


--
-- Data for Name: sessions; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity) FROM stdin;
dQTzArEMbnHkRgo2YebyibrJo2LFZITg2LKrDY1x	\N	172.19.0.1	Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36 OPR/129.0.0.0	YTozOntzOjY6Il90b2tlbiI7czo0MDoiWWhFOGFkSG5DaWgxV3RqMmRwUERadG1ReHUxOTBLN215Y3A1SE1IcCI7czo5OiJfcHJldmlvdXMiO2E6MTp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly9sb2NhbGhvc3Q6ODA4MCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=	1776670914
\.


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.users (id, name, email, password, role, is_approved, location, phone, birthdate, created_at, updated_at) FROM stdin;
1	chon	lee241203@uvg.edu.gt	$2y$12$uHIhLCqyRzlLydX5/Nuhv.YoPITb6RxoGLYZ19oVhFYqmrkKD3rz6	admin	t	guatemala	42888104	2005-12-21	2026-04-20 05:05:07	2026-04-20 05:10:29
3	wichandro	her241424@uvg.edu.gt	$2y$12$8DP3DSqAWLoTi08GoqbThu6HVZ92ii3GcBGTZgK0h35X0bK9ewsRu	admin	t	guatemala	12345678	2005-05-17	2026-04-20 05:20:17	2026-04-20 05:20:17
4	belen	mon231497@uvg.edu.gt	$2y$12$SSQNBqZdwmmRfFg7A..5fOtWMVDLQZY52uFU/VV/NUHYwkBRYIbBi	admin	t	guatemala	98765432	2005-04-07	2026-04-20 05:23:39	2026-04-20 05:23:39
5	sebastian	lem241155@uvg.edu.gt	$2y$12$BirLtLS5oBnEzJSEwPePbeCE.Wlj3jd5tYRLEnNvl108VYNUjdtEa	admin	t	guatemala	11112222	2005-06-07	2026-04-20 05:26:02	2026-04-20 05:26:02
6	jose	leejouman@gmail.com	$2y$12$fIDg7RALoOlC1dOTijvWTukeyT4jIXomxICMphdhqb9M3ktLmY42a	profesional	t	mixco	4777-6611	2005-01-09	2026-04-20 05:27:06	2026-04-20 05:27:36
7	arodi	arodi@gmail.com	$2y$12$2Y75xbvd6EVt2.zbD3cNoO6hK4viFXqoeGFJX6IQg0OFakcxnp/nG	familiar	t	guatemala	55555555	2007-05-30	2026-04-20 05:29:31	2026-04-20 05:30:34
8	pablo	pablo@gmail.com	$2y$12$/JhNFNHjpzoEbq9bbMyNH..qYVT7XifKGeDqYHMyMU8eIWUusXdFS	profesional	f	villa-nueva	44444444	2005-05-10	2026-04-20 05:32:22	2026-04-20 05:32:22
\.


--
-- Data for Name: usuarios; Type: TABLE DATA; Schema: public; Owner: cuidado_user
--

COPY public.usuarios (id, nombre, correo, password, rol, created_at, updated_at) FROM stdin;
\.


--
-- Name: actividad_id_actividad_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.actividad_id_actividad_seq', 1, false);


--
-- Name: adulto_mayor_id_adultomayor_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.adulto_mayor_id_adultomayor_seq', 1, false);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: medicamento_id_medicamento_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.medicamento_id_medicamento_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.migrations_id_seq', 7, true);


--
-- Name: personal_access_tokens_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 10, true);


--
-- Name: recordatorio_id_recordatorio_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.recordatorio_id_recordatorio_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.users_id_seq', 8, true);


--
-- Name: usuarios_id_seq; Type: SEQUENCE SET; Schema: public; Owner: cuidado_user
--

SELECT pg_catalog.setval('public.usuarios_id_seq', 1, false);


--
-- Name: actividad actividad_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.actividad
    ADD CONSTRAINT actividad_pkey PRIMARY KEY (id_actividad);


--
-- Name: adulto_mayor adulto_mayor_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.adulto_mayor
    ADD CONSTRAINT adulto_mayor_pkey PRIMARY KEY (id_adultomayor);


--
-- Name: asignacion_cuidador asignacion_cuidador_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.asignacion_cuidador
    ADD CONSTRAINT asignacion_cuidador_pkey PRIMARY KEY (iduser, id_adultomayor);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: medicamento medicamento_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.medicamento
    ADD CONSTRAINT medicamento_pkey PRIMARY KEY (id_medicamento);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: personal_access_tokens personal_access_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);


--
-- Name: personal_access_tokens personal_access_tokens_token_unique; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);


--
-- Name: recordatorio recordatorio_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.recordatorio
    ADD CONSTRAINT recordatorio_pkey PRIMARY KEY (id_recordatorio);


--
-- Name: registro_cumplimiento registro_cumplimiento_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.registro_cumplimiento
    ADD CONSTRAINT registro_cumplimiento_pkey PRIMARY KEY (id_actividad, iduser, fecha_cumplimiento);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: usuarios usuarios_correo_unique; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_correo_unique UNIQUE (correo);


--
-- Name: usuarios usuarios_pkey; Type: CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.usuarios
    ADD CONSTRAINT usuarios_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: cuidado_user
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: cuidado_user
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: cuidado_user
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: personal_access_tokens_tokenable_type_tokenable_id_index; Type: INDEX; Schema: public; Owner: cuidado_user
--

CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: cuidado_user
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: cuidado_user
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: actividad fk_actividad_adulto_mayor; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.actividad
    ADD CONSTRAINT fk_actividad_adulto_mayor FOREIGN KEY (id_adultomayor) REFERENCES public.adulto_mayor(id_adultomayor);


--
-- Name: adulto_mayor fk_adulto_mayor_administrador; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.adulto_mayor
    ADD CONSTRAINT fk_adulto_mayor_administrador FOREIGN KEY (idadministrador) REFERENCES public.users(id);


--
-- Name: asignacion_cuidador fk_asignacion_adulto_mayor; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.asignacion_cuidador
    ADD CONSTRAINT fk_asignacion_adulto_mayor FOREIGN KEY (id_adultomayor) REFERENCES public.adulto_mayor(id_adultomayor);


--
-- Name: asignacion_cuidador fk_asignacion_user; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.asignacion_cuidador
    ADD CONSTRAINT fk_asignacion_user FOREIGN KEY (iduser) REFERENCES public.users(id);


--
-- Name: medicamento fk_medicamento_adulto_mayor; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.medicamento
    ADD CONSTRAINT fk_medicamento_adulto_mayor FOREIGN KEY (id_adultomayor) REFERENCES public.adulto_mayor(id_adultomayor);


--
-- Name: recordatorio fk_recordatorio_actividad; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.recordatorio
    ADD CONSTRAINT fk_recordatorio_actividad FOREIGN KEY (id_actividad) REFERENCES public.actividad(id_actividad);


--
-- Name: registro_cumplimiento fk_registro_actividad; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.registro_cumplimiento
    ADD CONSTRAINT fk_registro_actividad FOREIGN KEY (id_actividad) REFERENCES public.actividad(id_actividad);


--
-- Name: registro_cumplimiento fk_registro_user; Type: FK CONSTRAINT; Schema: public; Owner: cuidado_user
--

ALTER TABLE ONLY public.registro_cumplimiento
    ADD CONSTRAINT fk_registro_user FOREIGN KEY (iduser) REFERENCES public.users(id);


--
-- PostgreSQL database dump complete
--

\unrestrict tKXmLbtyh1WQ2vcPFtztmdIsuHWTUP5GHETTe36TJSiTOFufIZMNiDRYbthw8Qi

