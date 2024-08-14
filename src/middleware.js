import { NextResponse } from "next/server";
import jwt from "jsonwebtoken";

export function middleware(request) {
  const x = request.cookies.get("jwt");
  const y = x && typeof x === "object" ? x.value : x;

  let token;

  console.log("middleware token")
  try {
    token = jwt.verify(y, process.env.JWT_SECRET); 
  } catch (err) {

    token = null;
  }

  const reqpath = request.nextUrl.pathname;
  const isLoginOrRegisterPage = reqpath === "/login" || reqpath === "/register";

  // Redirect if the user is logged in and trying to access login or register page
  if (isLoginOrRegisterPage && token) {
    return NextResponse.redirect(new URL("/", request.url));
  }

  // Redirect if the user is not logged in and trying to access restricted pages
  if (!token && !isLoginOrRegisterPage) {
    return NextResponse.redirect(new URL("/login", request.url));
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/login", "/register", ],
};
