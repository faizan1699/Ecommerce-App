import { NextResponse } from "next/server";

export function middleware(request) {
  const token = request.cookies.get("jwt")?.value || null;
  const reqpath = request.nextUrl.pathname;

  const isLoginOrRegisterPage = reqpath === "/login" || reqpath === "/register";

  if (isLoginOrRegisterPage && token) {
    return NextResponse.redirect(new URL("/", request.url));
  }

  if (!token && !isLoginOrRegisterPage) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: [
    "/login",
    "/register",
  ],
};
