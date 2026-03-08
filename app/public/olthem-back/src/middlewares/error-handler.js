export function notFoundHandler(req, res) {
  res.status(404).json({ error: 'Route not found' });
}

export function errorHandler(err, req, res, next) {
  // eslint-disable-line no-unused-vars
  console.error(err);
  res.status(err.statusCode || 500).json({
    error: err.message || 'Unexpected server error',
  });
}
