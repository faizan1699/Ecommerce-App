/** @type {import('next').NextConfig} */
const nextConfig = {
  webpack: (config, { dev }) => {
    if (!dev) {
      config.devtool = false; // Disable source maps in production
    }
    return config;
  },
  productionBrowserSourceMaps: false,
};

export default nextConfig;
