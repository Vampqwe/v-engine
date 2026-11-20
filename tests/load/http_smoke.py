#!/usr/bin/env python3
"""Small bounded HTTP smoke/load runner for a local V-engine deployment."""

import argparse
import concurrent.futures
import math
import statistics
import time
import urllib.error
import urllib.parse
import urllib.request


LOCAL_HOSTS = {"localhost", "127.0.0.1", "::1"}


def percentile(samples, value):
    ordered = sorted(samples)
    if not ordered:
        return 0.0
    index = max(0, math.ceil(value * len(ordered)) - 1)
    return ordered[index]


def worker(url, deadline, timeout):
    samples = []
    failures = 0

    while time.monotonic() < deadline:
        started = time.monotonic()
        try:
            with urllib.request.urlopen(url, timeout=timeout) as response:
                response.read()
                if not 200 <= response.status < 400:
                    failures += 1
        except (urllib.error.URLError, TimeoutError, OSError):
            failures += 1
        samples.append((time.monotonic() - started) * 1000)

    return samples, failures


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--url", default="http://127.0.0.1:8000/")
    parser.add_argument("--duration", type=int, default=10, help="test duration in seconds (1-300)")
    parser.add_argument("--workers", type=int, default=4, help="concurrent clients (1-32)")
    parser.add_argument("--timeout", type=float, default=3.0, help="per-request timeout in seconds")
    parser.add_argument(
        "--allow-remote",
        action="store_true",
        help="allow testing non-local hosts; use only against systems you own or are authorized to test",
    )
    args = parser.parse_args()

    parsed = urllib.parse.urlsplit(args.url)
    if parsed.scheme not in {"http", "https"} or not parsed.hostname:
        parser.error("--url must be an absolute HTTP or HTTPS URL")
    if parsed.username or parsed.password:
        parser.error("credentials must not be embedded in the URL")
    if parsed.hostname not in LOCAL_HOSTS and not args.allow_remote:
        parser.error("non-local URLs require --allow-remote")
    if not 1 <= args.duration <= 300:
        parser.error("--duration must be between 1 and 300 seconds")
    if not 1 <= args.workers <= 32:
        parser.error("--workers must be between 1 and 32")
    if not 0.1 <= args.timeout <= 30:
        parser.error("--timeout must be between 0.1 and 30 seconds")

    deadline = time.monotonic() + args.duration
    started = time.monotonic()
    all_samples = []
    failures = 0

    with concurrent.futures.ThreadPoolExecutor(max_workers=args.workers) as executor:
        futures = [
            executor.submit(worker, args.url, deadline, args.timeout)
            for _ in range(args.workers)
        ]
        for future in concurrent.futures.as_completed(futures):
            samples, worker_failures = future.result()
            all_samples.extend(samples)
            failures += worker_failures

    elapsed = time.monotonic() - started
    requests = len(all_samples)
    print(f"URL: {args.url}")
    print(f"Duration: {elapsed:.2f}s; workers: {args.workers}")
    print(f"Requests: {requests}; failures: {failures}; throughput: {requests / elapsed:.2f} req/s")
    print(
        "Latency ms: "
        f"p50={percentile(all_samples, 0.50):.2f} "
        f"p95={percentile(all_samples, 0.95):.2f} "
        f"p99={percentile(all_samples, 0.99):.2f} "
        f"mean={statistics.fmean(all_samples) if all_samples else 0.0:.2f}"
    )

    if failures:
        raise SystemExit(1)


if __name__ == "__main__":
    main()
