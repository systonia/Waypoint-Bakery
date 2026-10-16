-- The bakery's staff, and the sales per day and product the charts read
CREATE TABLE users (
    id serial PRIMARY KEY,
    email text NOT NULL UNIQUE,
    name text NOT NULL,
    password_hash text NOT NULL,
    role text NOT NULL CHECK (role IN ('owner', 'staff'))
);

CREATE TABLE daily_sales (
    day date NOT NULL,
    product_id integer NOT NULL REFERENCES products,
    quantity integer NOT NULL,
    revenue_cents integer NOT NULL,
    PRIMARY KEY (day, product_id)
);

-- When a background job last ran, for all workers and processes together (see SalesReport::aggregate())
CREATE TABLE jobs (
    name text PRIMARY KEY,
    last_run timestamptz NOT NULL
);
