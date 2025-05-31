export const parseList = (list) => {
  return list?.split(",")?.map((v) => v.trim()) || [];
};
